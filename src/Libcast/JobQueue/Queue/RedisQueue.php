<?php

/*
 * This file is part of Libcast JobQueue component.
 *
 * (c) Brice Vercoustre <brcvrcstr@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Libcast\JobQueue\Queue;

use Libcast\JobQueue\Exception\QueueException;
use Libcast\JobQueue\Queue\AbstractQueue;
use Libcast\JobQueue\Queue\QueueInterface;
use Libcast\JobQueue\Job\NullJob;
use Libcast\JobQueue\Task\Task;
use Libcast\JobQueue\Task\TaskInterface;
use Libcast\JobQueue\Notification\Notification;

/**
 * Redis Queue uses the following keys:
 *
 * $prefix:task:lastid                string (incr)     unique Task id
 * $prefix:task:$id                   string            stores Task data (json)
 * $prefix:task:$id:children:finished string (incr)     counts Task's finished children
 * $prefix:task:failed:$id            string (incr)     counts a Task failed atempts
 * $prefix:task:scheduled             sorted set        lists scheduled Tasks
 * $prefix:task:scheduled:$id         string            stores scheduled Task data (json)
 * $prefix:task:finished              list              lists finished Tasks (logs)
 * $prefix:profile:$profile           sorted set        lists Tasks from a Queue's profile
 * $prefix:profile:common             sorted set        lists all Tasks from Queue
 * $prefix:union:$hash                sorted set union  union of Queue profiles (temporary)
 */
class RedisQueue extends AbstractQueue implements QueueInterface
{
    const PREFIX = 'libcast:jobqueue:';

    const SCORE_UNQUEUED = -1;

    const SCORE_RUNNING = 0;

    /**
     * {@inheritdoc}
     */
    public function add(TaskInterface $task, $first = true)
    {
        if (!$task->getId()) {
            // give the Task a uniq Id
            $task->setId($id = $this->client->incr(self::PREFIX.'task:lastid'));
        }

        $pipe = $this->client->pipeline();

        if (!$task->getScheduledAt() || $task->getScheduledAt(false) <= time()) {
            // only put in Queue non scheduled or immediate Tasks
            $task->setStatus(Task::STATUS_WAITING);

            // store Task data
            $pipe->set(self::PREFIX."task:{$task->getId()}", $task->jsonExport());

            // affect Task to its Queue profile's set
            $pipe->zadd(self::PREFIX."profile:{$task->getOption('profile')}",
                    $task->getOption('priority'),
                    $task->getId());

            // add Task to Queue's common set
            $pipe->zadd(self::PREFIX.'profile:'.self::COMMON_PROFILE,
                    $task->getOption('priority'),
                    $task->getId());
        } else {
            $this->schedule($task, $task->getScheduledAt(false));
        }

        $parent_id = $task->getParentId();
        if ($parent_id && $parent = $this->getTask($parent_id)) {
            // update parent Task
            $parent->updateChild($task);
            $this->update($parent);
        }

        $pipe->execute();

        return $task->getId();
    }

    /**
     * Persist Task data into Queue, update parent
     *
     * @param \Libcast\JobQueue\Task\TaskInterface $task
     */
    protected function save(TaskInterface $task)
    {
        $parent_id = $task->getParentId();
        if ($parent_id && $parent = $this->getTask($parent_id)) {
            $parent->updateChild($task);
            $this->update($parent);
        }

        $this->log("Task '{$task->getId()}' saved", array(), 'debug');

        return $this->client->set(self::PREFIX."task:{$task->getId()}", $task->jsonExport());
    }

    /**
     * {@inheritdoc}
     */
    public function update(TaskInterface $task)
    {
        $this->log("Task '{$task->getId()}' updated", array(
            'status'    => $task->getStatus(),
            'progress'  => $task->getProgress(true),
            'children'  => count($task->getChildren()),
        ), 'debug');

        $enqueued = $this->getTask($task->getId());

        if ($task->getStatus() !== $enqueued->getStatus()) {
            // if status change, trigger some extra actions
            $status = ucfirst(strtolower($task->getStatus()));
            $method = "set{$status}Status";

            if (method_exists($this, $method)) {
                $task = $this->$method($task);
            }
        } elseif ($task->getOption('priority') !== $enqueued->getOption('priority')) {
            // if priority change, edit score
            switch ($task->getStatus()) {
                case Task::STATUS_PENDING:
                case Task::STATUS_SUCCESS:
                case Task::STATUS_FAILED:
                    $this->setScore($task, self::SCORE_UNQUEUED);
                case Task::STATUS_WAITING:
                    $this->setScore($task, (int) $task->getOption('priority'));
                    break;
            }
        }

        // persist data only if extra action returns a Task
        if ($task instanceof TaskInterface) {
            $this->save($task);
        }
    }

    /**
     *
     * @param \Libcast\JobQueue\Task\TaskInterface $task
     * @return mixed
     */
    public function setPendingStatus(TaskInterface $task)
    {
        $task->setProgress(0);

        return $task;
    }

    /**
     *
     * @param \Libcast\JobQueue\Task\TaskInterface $task
     * @return mixed
     */
    public function setWaitingStatus(TaskInterface $task)
    {
        $enqueued = $this->getTask($task->getId());
        $this->setScore($task, $enqueued->getOption('priority'));

        $task->setProgress(0);

        return $task;
    }

    /**
     *
     * @param \Libcast\JobQueue\Task\TaskInterface $task
     * @return mixed
     */
    public function setRunningStatus(TaskInterface $task)
    {
        $this->setScore($task, self::SCORE_RUNNING);

        $task->setProgress(0);

        return $task;
    }

    /**
     *
     * @param \Libcast\JobQueue\Task\TaskInterface $task
     * @return mixed
     */
    public function setSuccessStatus(TaskInterface $task)
    {
        $this->setScore($task, self::SCORE_UNQUEUED);

        $task->setProgress(1);

        return $task;
    }

    /**
     *
     * @param \Libcast\JobQueue\Task\TaskInterface $task
     * @return mixed
     */
    public function setFailedStatus(TaskInterface $task)
    {
        // send notification
        if ($this->getMailer() && $notification = $task->getNotification()) {
            $notification->setMailer($this->getMailer());
            $notification->sendNotification(Notification::TYPE_ERROR);

            $this->log('An error notification has been sent.');
        }

        $this->setScore($task, self::SCORE_UNQUEUED);

        return $task;
    }

    /**
     *
     * @param \Libcast\JobQueue\Task\TaskInterface $task
     * @return null
     */
    public function setFinishedStatus(TaskInterface $task)
    {
        $parent_id = $task->getParentId();
        if ($parent_id && $parent = $this->getTask($parent_id)) {
            // count parent's finished children
            $this->incrFinishedChildren($parent);

            if ($this->isComplete($parent)) {
                // if all children Tasks have been executed,
                // mark the parent Task as finished,
                // this will recursively mark all parent jobs as finished
                $this->setFinishedStatus($parent);
            }
        } else {
            $this->remove($task);
        }

        // add Task to the finished list
        $this->client->lpush(self::PREFIX.'task:finished', $task->getId());

        // send notification
        if ($this->getMailer() && $notification = $task->getNotification()) {
            $notification->setMailer($this->getMailer());
            $notification->sendNotification(Notification::TYPE_SUCCESS);

            $this->log('A success notification has been sent.');
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function remove(TaskInterface $task, $update_parent = true, $delete_failed_count = true)
    {
        foreach ($task->getChildren() as $child) {
            $this->remove($child, false);
        }

        if (!$task->getId()) {
            return false;
        }

        $pipe = $this->client->pipeline();

        $parent_id = $task->getParentId();
        if ($update_parent && $parent_id && $parent = $this->getTask($parent_id)) {
            // update parent Task
            $parent->removeChild($task);
            $this->update($parent);
        }

        $pipe->del(self::PREFIX."task:$task");
        $pipe->del(self::PREFIX."task:$task:children:finished");
        $pipe->del(self::PREFIX."task:scheduled:$task");
        $pipe->zrem(self::PREFIX."profile:{$task->getOption('profile')}", $task->getId());
        $pipe->zrem(self::PREFIX.'profile:'.self::COMMON_PROFILE, $task->getId());
        $pipe->zrem(self::PREFIX.'task:scheduled', $task->getId());

        if ($delete_failed_count) {
            $pipe->del(self::PREFIX."task:failed:$task");
        }

        return $pipe->execute();
    }

    /**
     * Edit the Task entry score from its Redis profiled sets
     *
     * @param \Libcast\JobQueue\Task\TaskInterface $task
     * @param int $score
     * @throws \Libcast\JobQueue\Exception\QueueException
     */
    protected function setScore(TaskInterface $task, $score)
    {
        if (!is_int($score) || ($score < self::PRIORITY_MIN && !in_array($score, array(
            self::SCORE_RUNNING,
            self::SCORE_UNQUEUED,
        )))) {
            throw new QueueException("The Task '$task' can't have score '$score'.");
        }

        $pipe = $this->client->pipeline();

        // update Task from its Queue profile's set
        $pipe->zrem(self::PREFIX."profile:{$task->getOption('profile')}", $task->getId());
        $pipe->zadd(self::PREFIX."profile:{$task->getOption('profile')}", $score, $task->getId());

        // update Task from common Queue profile's set
        $pipe->zrem(self::PREFIX.'profile:'.self::COMMON_PROFILE, $task->getId());
        $pipe->zadd(self::PREFIX.'profile:'.self::COMMON_PROFILE, $score, $task->getId());

        return $pipe->execute();
    }

    /**
     * Increment the count of finished children of a Task
     *
     * @param   \Libcast\JobQueue\Task\Task $task
     * @throws  \Libcast\JobQueue\Exception\QueueException
     */
    protected function incrFinishedChildren(TaskInterface $task)
    {
        if (!$task->getId()) {
            throw new QueueException("Impossible to increment finished count for Task '$task'.");
        }

        $parent_id = $task->getParentId();
        if ($parent_id && $parent = $this->getTask($parent_id)) {
            $this->incrFinishedChildren($parent);
        }

        return $this->client->incr(self::PREFIX."task:$task:children:finished");
    }

    /**
     * Check if all the children of a Task are finished
     *
     * @param   \Libcast\JobQueue\Task\Task $task
     * @return  int
     * @throws  \Libcast\JobQueue\Exception\QueueException
     */
    protected function isComplete(TaskInterface $task)
    {
        if (!$task->getId()) {
            throw new QueueException("Impossible to count finished children of Task '$task'.");
        }

        $finished = $this->client->get(self::PREFIX."task:$task:children:finished");
        $total    = $task->countChildren();

        return (int) $finished === (int) $total;
    }

    /**
     * {@inheritdoc}
     */
    public function incrFailed(TaskInterface $task)
    {
        if (!$task->getId()) {
            throw new QueueException("Impossible to increment failure count for Task '$task'.");
        }

        return $this->client->incr(self::PREFIX."task:failed:$task");
    }

    /**
     * {@inheritdoc}
     */
    public function countFailed(TaskInterface $task)
    {
        if (!$task->getId()) {
            throw new QueueException("Impossible to count failures for Task '$task'.");
        }

        return (int) $this->client->get(self::PREFIX."task:failed:$task");
    }

    /**
     * {@inheritdoc}
     */
    public function schedule(TaskInterface $task, $date)
    {
        $task->setStatus(Task::STATUS_PENDING);
        $task->setScheduledAt(date('Y-m-d H:i:s', $date));

        // remove Task from Queue but keep failed count
        $this->remove($task, true, false);

        $pipe = $this->client->pipeline();

        // store Task data in a dedicated key
        $pipe->set(self::PREFIX."task:scheduled:$task", $task->jsonExport());

        // add Task to sheduled set with time as score
        $pipe->zadd(self::PREFIX.'task:scheduled',
                $task->getScheduledAt(false),
                $task->getId());

        return $pipe->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function unschedule(TaskInterface $task)
    {
        $pipe = $this->client->pipeline();

        // store Task data in a dedicated key
        $pipe->del(self::PREFIX."task:scheduled:{$task->getId()}");

        // add Task to sheduled set with time as score
        $pipe->zrem(self::PREFIX.'task:scheduled', $task->getId());

        $pipe->execute();

        return $this->add($task, false);
    }

    /**
     * Enqueue all Tasks scheduled for now
     */
    protected function unscheduleMatureTasks()
    {
        foreach ($this->client->zrangebyscore(self::PREFIX.'task:scheduled', 0, time()) as $id) {
            // enqueue Task
            $this->unschedule($this->getTask($id));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getTasks($sort_by = null, $sort_order = null, $priority = null, $profile = null, $status = null)
    {
        // check for scheduled Tasks that needs to be enqueued
        $this->unscheduleMatureTasks();

        if (!$sort_by) {
            $sort_by = self::SORT_BY_PRIORITY;
        }

        if (!$sort_order) {
            $sort_order = self::ORDER_ASC;
        }

        if (!in_array($sort_by, self::getSortByOptions())) {
            throw new QueueException("Sort by option must be priority|profile|status. Value '$sort_by' given.");
        }

        if (!$profile) {
            $profile = self::COMMON_PROFILE;
        }

        $status = $status === 'all' ? null : $status;
        if ($status && !in_array($status, Task::getStatuses())) {
            throw new QueueException("Status '$status' does not exists.");
        }

        if ($priority && (!is_int($priority) || $priority < self::PRIORITY_MIN)) {
            throw new QueueException("Priority '$priority' is not valid.");
        }

        $tasks = array();

        // Queue does not store pending Tasks
        // Sheduled Tasks are pending
        if (Task::STATUS_PENDING === $status || !$status) {
            foreach ($this->client->zrange(self::PREFIX.'task:scheduled', 0, -1) as $id) {
                $task = $this->getTask($id);
                if ($task
                        && (self::COMMON_PROFILE === $profile || $profile === $task->getOption('profile'))
                        && (!$priority || $priority === $task->getOption('priority'))
                        && (!$status || $status === $task->getStatus())) {
                    $tasks[] = $task;
                }
            }
        }

        // Queue stores waiting Tasks with a positive score
        if (Task::STATUS_WAITING === $status || !$status) {
            $key = self::PREFIX.'profile:'.($profile ? $profile : self::COMMON_PROFILE);

            foreach ($this->client->zrevrangebyscore($key,
                    $priority ? '('.($priority + 1) : '+inf',
                    $priority ? $priority : self::PRIORITY_MIN) as $id) {
                $task = $this->getTask($id);
                if ($task && (!$status || $status === $task->getStatus())) {
                    $tasks[] = $task;
                }
            }
        }

        // Queue stores running and successfull Tasks the same way until Task is
        // finished. Failed Tasks are immediately requeued or remain untouched.
        if (!$status || in_array($status, array(
            Task::STATUS_RUNNING,
            Task::STATUS_SUCCESS,
            Task::STATUS_FAILED,
        ))) {
            $key = self::PREFIX.'profile:'.($profile ? $profile : self::COMMON_PROFILE);

            foreach ($this->client->zrangebyscore($key, -1, '(1') as $id) {
                $task = $this->getTask($id);
                if ($task
                        && (!$status || $status === $task->getStatus())
                        && (!$priority || $priority === $task->getOption('priority'))) {
                    $tasks[] = $task;
                }
            }
        }

        // finished Tasks have no data (their ids are just listed) so we have to
        // create fake Tasks based on NullJob
        if (Task::STATUS_FINISHED === $status && self::COMMON_PROFILE === $profile) {
            foreach ($this->client->lrange(self::PREFIX.'task:finished', 0, -1) as $id) {
                $task = new Task(new NullJob);
                $task->setId($id);
                $task->setStatus(Task::STATUS_FINISHED);
                $task->setProgress(1);

                $tasks[] = $task;
            }
        }

        // group Tasks by 'sort_by' option
        $groups = array();
        foreach ($tasks as $task) {
            switch ($sort_by) {
                case self::SORT_BY_PROFILE:
                    $sort = $task->getOption('profile');
                    break;

                case self::SORT_BY_STATUS:
                    $sort = $task->getStatus();
                    break;

                case self::SORT_BY_PRIORITY:
                default :
                    $sort = $task->getOption('priority');
            }

            $groups[$sort][$task->getId()] = $task;
        }

        // order all members within groups
        foreach ($groups as $key => $array) {
            if (self::ORDER_ASC === $sort_order) {
                ksort($array);
            } elseif (self::SORT_BY_PRIORITY === $sort_by) {
                ksort($array);
            } else {
                krsort($array);
            }

            $groups[$key] = $array;
        }

        // order each group
        self::ORDER_ASC === $sort_order ? ksort($groups) : krsort($groups);

        // list Tasks, keeping arrays order
        $list = array();
        foreach ($groups as $group) {
            foreach ($group as $task) {
                $list[] = $task;
            }
        }

        return $list;
    }

    /**
     * {@inheritdoc}
     */
    public function getTask($id)
    {
        if (!$id) {
            return null;
        }

        // get Task from Queue
        if ($enqueued = $this->client->get(self::PREFIX."task:$id")) {
            return Task::jsonImport($enqueued);
        }

        // get from finished Tasks
        if ($this->client->lrem(self::PREFIX.'task:finished', 0, $id)) {
            $this->client->lpush(self::PREFIX.'task:finished', $id);

            $task = new Task(new NullJob);
            $task->setId($id);
            $task->setStatus(Task::STATUS_FINISHED);
            $task->setProgress(1);

            return $task;
        }

        // get Scheduled Tasks
        if ($scheduled = $this->client->get(self::PREFIX."task:scheduled:$id")) {
            return Task::jsonImport($scheduled);
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getTaskStatus($id)
    {
        if ($task = $this->getTask($id)) {
            // if Task is currently in Queue, check if any child has failed
            foreach ($task->getChildren() as $child) {
                if (Task::STATUS_FAILED === $this->getTaskStatus($child->getId())) {
                    return Task::STATUS_FAILED;
                }
            }

            return $task->getStatus();
        } else  {
            // else return STATUS_PENDING
            return Task::STATUS_PENDING;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getNextTask($profiles = null)
    {
        $task = null;

        // check for scheduled Tasks that needs to be enqueued
        $this->unscheduleMatureTasks();

        switch (true) {
            // get next task from an array of profiles
            case is_array($profiles) && count($profiles) > 1 :
                // generate a key to store a union of profile's sets
                $hash = md5(serialize($profiles));
                $key = self::PREFIX."union:$hash";
                $delete_key = true;

                // union only profiles that are not empty
                $keys = array();
                $last_profile = null;
                foreach ($profiles as $profile) {
                    if ($this->client->zcount(self::PREFIX."profile:$profile", self::PRIORITY_MIN, '+inf')) {
                        $keys[] = self::PREFIX."profile:$profile";
                        $last_profile = $profile;
                    }
                }

                if (count($keys) <= 1) {
                    $one_profile = reset($profiles);
                    $profile = $last_profile ? $last_profile : $one_profile;
                    $key = self::PREFIX."profile:$profile";
                    $delete_key = false;

                    break;
                }

                // create a temporary sorted list (union of profiles)
                call_user_func_array(
                        array($this->client, 'zunionstore'),
                        array_merge(
                                array($key, count($keys)),
                                $keys,
                                array(array('AGGREGATE MAX'))));

                break;

            // get next task from a single profile
            case is_string($profiles) || (is_array($profiles) && count($profiles) === 1) :
                if (is_array($profiles)) {
                    $profiles = reset($profiles);
                }

                $key = self::PREFIX."profile:$profiles";
                $delete_key = false;

                break;

            // get next task from the common queue (all profiles)
            default :
                $key = self::PREFIX.'profile:'.self::COMMON_PROFILE;
                $delete_key = false;
        }

        // get all non reserved Tasks ordered by priority
        $tasks_ids = $this->client->zrevrangebyscore($key, '+inf', self::PRIORITY_MIN);
        if (count($tasks_ids) > 0) {
            /* @hack to fix FIFO order */
            // reduce Tasts set to those of highest priority
            $next_priority_id   = reset($tasks_ids);
            $next_priority      = $this->client->zscore($key, $next_priority_id);
            $priority_task_ids  = $this->client->zrevrangebyscore($key,
                    $next_priority,
                    sprintf('(%d', $next_priority - 1));

            // sort ids in ASC order to respect FIFO rule
            sort($priority_task_ids);

            // find a valid Task
            foreach ($priority_task_ids as $task_id) {
                try {
                    $task = Task::jsonImport($this->client->get(self::PREFIX."task:$task_id"));
                    break;
                } catch (Exception $e) {
                    continue;
                }
            }
        }

        // delete temporary keys
        if ($delete_key) {
            $this->client->del($key);
        }

        return $task;
    }

    /**
     * {@inheritdoc}
     */
    public function reboot(array $profiles = array())
    {
        $profiles = array_merge($profiles, array(self::COMMON_PROFILE));
        foreach ($profiles as $profile) {
            $key = self::PREFIX."profile:$profile";
            $members = $this->client->zrangebyscore($key, 0, '('.self::PRIORITY_MIN);
            foreach ($members as $member) {
                if ($task = $this->getTask($member)) {
                    // requeue Task
                    $task->setStatus(Task::STATUS_WAITING);
                    $this->update($task);
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        $pipe = $this->client->pipeline();

        foreach ($this->client->keys(self::PREFIX.'*') as $key) {
            $pipe->del($key);
        }

        return $pipe->execute();
    }
}