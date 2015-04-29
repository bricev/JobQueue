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
use Libcast\JobQueue\Task;

/**
 * Redis Queue uses the following keys:
 *
 * jobqueue:queue:count
 * jobqueue:tasks:{profile}:{status}
 * jobqueue:task:{id}
 */
class RedisQueue extends AbstractQueue implements QueueInterface
{
    const PREFIX = 'jobqueue';

    /**
     *
     * @param Task $task
     * @param null $position
     * @return Task
     * @throws \Libcast\JobQueue\Exception\TaskException
     */
    protected function insert(Task $task, $position = null)
    {
        if (!$task->getId()) {
            // give the Task a unique Id
            $task->setId($this->getClient()->incr(self::key('queue:count')));
        }

        $task->setStatus(Task::STATUS_WAITING);

        // persist Task data
        $this->getClient()->set(
            self::key('task', $task->getId()),
            json_encode($task)
        );

        $key = self::key('tasks', $task->getProfile(), $task->getStatus());
        // This will always be a :waiting list

        switch ($position) {
            // Set Task at one end or the other of the Queue
            case 'head':
            case 'tail':
                $push = 'head' === $position ? 'lpush' : 'rpush';
                $this->getClient()->$push($key, $task->getId());
                break;

            // Set Task after $position inside the Queue
            default :
                // Always find the insertion position compared to root ancestors
                if ($position instanceof Task) {
                    $position = $position->getRootId();
                } else if ($sibling = $this->getTask($position)) {
                    $position = $sibling->getRootId();
                }

                // If $key is empty, then just tail the Task to the list
                $competitor_ids = $this->getClient()->lrange($key, 0, -1);
                if (!is_array($competitor_ids) or empty($competitor_ids)) {
                    $this->getClient()->rpush($key, $task->getId());
                    break;
                }

                // List root ancestors for each listed Task
                $competitor_roots = [];
                foreach ($competitor_ids as $rank => $id) {
                    // Collect ancestor id if exists
                    $competitor_id = ($competitor = $this->getTask($id)) ?
                            $competitor->getRootId() :
                            $id;

                    // Avoid having two competitors with the same root ancestor
                    if (in_array($competitor_id, $competitor_roots)) {
                        // How is the Task compared to current competitor?
                        $has_priority = $position < $competitor_id ? true : false;
                        $is_family = (int) $position === (int) $competitor_id ? true : false;
                        $is_older = $task->getId() < $id;

                        if ($has_priority or ($is_family and $is_older)) {
                            // If the Task has the priority compared to the current ancestor,
                            // then only keep the last ancestor in the list
                            // so that the Task can be positioned after
                            foreach (array_keys($competitor_roots, $competitor_id) as $competitor_rank) {
                                unset($competitor_roots[$competitor_rank]);
                            }
                        } else {
                            // Otherwise continue to only keep the first competitor in the list
                            // so that the Task can be positioned before
                            continue;
                        }
                    }

                    $competitor_roots[$rank] = $competitor_id;
                }

                // The real position should be the closest $id from $position
                $deviations = [];
                foreach ($competitor_roots as $rank => $id) {
                    $deviations[$rank] = abs((int) $id - (int) $position);
                }

                asort($deviations);
                $keys = array_keys($deviations);
                $winner = reset($keys);

                $id = $competitor_ids[$winner];
                $place = $position > $id ? 'after' : 'before';

                // Insert the Task at the right position
                $this->getClient()->linsert($key, $place, $id, $task->getId());
                break;
        }

        return $task;
    }

    /**
     *
     * @param Task $task
     * @return int|Task
     */
    public function enqueue(Task $task)
    {
        return $this->insert($task, 'head');
    }

    /**
     *
     * @param Task $task
     * @param null $position
     * @return Task|mixed
     */
    public function shift(Task $task, $position = null)
    {
        return $this->insert($task, $position ? $position : 'tail');
    }

    /**
     * Updates the Task as follow:
     * - if `profile` changes, Task ID is moved to the right list
     * - if `profile` changes, some settings may be applied
     * - if the Task has parents, some settings may be applied to those
     * - Task data is persisted
     *
     * @param Task $task
     */
    public function update(Task $task)
    {
        // Get the Task as it has been persisted in Redis
        $persistedTask = $this->getTask($task->getId());

        // Settings that occur only when the profile changes
        if ($task->getStatus() !== $persistedTask->getStatus()) {
            // Remove Task from source listing
            $source = self::key('tasks',
                    $persistedTask->getProfile(),
                    $persistedTask->getStatus());

            $this->getClient()->lrem($source, 0, $persistedTask->getId());

            // Prepend Task to destination listing
            $destination = self::key('tasks',
                    $task->getProfile(),
                    $task->getStatus());

            $this->getClient()->lpush($destination, $task->getId());

            // Force progress
            switch ($task->getStatus()) {
                case Task::STATUS_PENDING:
                case Task::STATUS_WAITING:
                case Task::STATUS_RUNNING:
                    $task->setProgress(0);
                    break;

                case Task::STATUS_SUCCESS:
                case Task::STATUS_FINISHED:
                    $task->setProgress(1);
                    break;
            }
        }

        // Persist data before any cascading updates (see below)
        $this->getClient()->set(
            self::key('task', $task->getId()),
            json_encode($task)
        );

        // Interactions with parents
        if ($parent = $this->getTask($task->getParentId())) {
            switch ($task->getStatus()) {
                case Task::STATUS_FINISHED:
                    // Mark the parent as `finished` only if all of its children are done too
                    $finished = true;
                    foreach ($parent->getChildren() as $child) { /* @var $child \Libcast\JobQueue\Task */
                        $sibling = $this->getTask($child->getId());
                        if ($sibling instanceof Task
                                and Task::STATUS_FINISHED !== $sibling->getStatus()
                                and $task->getId() !== $sibling->getId()) {
                            $finished = false;
                            break;
                        }
                    }

                    if ($finished) {
                        $parent->setStatus(Task::STATUS_FINISHED);
                        $this->update($parent);
                    }
                    break;

                case Task::STATUS_FAILED:
                    // Avoid infinite loop when cascading up
                    if (Task::STATUS_FAILED === $parent->getStatus()) {
                        continue;
                    }

                    $parent->setStatus(Task::STATUS_FAILED);
                    $this->update($parent);
                    break;
            }
        }

        // Interactions with children
        if ($children = $task->getChildren()) {
            switch ($task->getStatus()) {
                case Task::STATUS_FAILED:
                    foreach ($children as $child) {
                        // Children may not have been enqueued yet
                        if (!$child = $this->getTask($child)) {
                            continue;
                        }

                        // Avoid infinite loop when cascading down
                        if (Task::STATUS_FAILED === $child->getStatus()) {
                            continue;
                        }

                        $child->setStatus(Task::STATUS_FAILED);
                        $this->update($child);
                    }
                    break;
            }
        }
    }

    /**
     *
     * @param Task $task
     */
    public function delete(Task $task)
    {
        // Remove Task from its listing
        $this->getClient()->lrem(
            self::key('tasks', $task->getProfile(), $task->getStatus()),
            0, $task->getId()
        );

        // Remove Task data
        $this->getClient()->del(self::key('task', $task->getId()));

        // Recursively delete all parents
        if ($parent = $this->getTask($task->getParentId())) {
            $this->delete($parent);
        }

        // Recursively delete all children
        foreach ($task->getChildren() as $child) {
            if ($child = $this->getTask($child)) {
                $this->delete($child);
            }
        }
    }

    /**
     *
     * @see http://redis.io/commands/brpoplpush
     * @param string $profile
     * @return Task|null|void
     */
    public function fetch($profile)
    {
        try
        {
            $id = $this->getClient()->brpoplpush(
                self::key("tasks:$profile:waiting"),
                self::key("tasks:$profile:running"),
                300 // 5 minutes idle connection
            );
        } catch (\Exception $e) {
            // sleep a little to avoid high CPU consuming infinite loops...
            sleep(3);

            // ... and try again
            return $this->fetch($profile);
        }

        $task = $this->getTask($id);
        $task->setStatus(Task::STATUS_RUNNING);
        $this->update($task);

        return $task;
    }

    /**
     * Flushes the Queue from all of its Tasks
     *
     * @return boolean
     */
    public function flush()
    {
        $pipe = $this->getClient()->pipeline();
        $keys = $this->getClient()->keys(self::key('*'));
        foreach ($keys as $key) {
            $pipe->del($key);
        }

        return $pipe->execute();
    }

    /**
     *
     * @param null $filter_by_profile
     * @param null $filter_by_status
     * @param string $sort_by_order
     * @return array
     */
    public function getTasks($filter_by_profile = null, $filter_by_status = null, $sort_by_order = 'asc')
    {
        // Get all list's keys filtered by profile and status
        if (!$filter_by_profile and !$filter_by_status) {
            $keys = $this->getClient()->keys(self::key('tasks:*:*'));

            // Hide `finished` Tasks when listing everything
            $keys = array_filter($keys, function ($value) {
                return !preg_match('/^'. self::PREFIX . ':tasks:([^:]+):' . Task::STATUS_FINISHED . '$/', $value);
            });
        } elseif (!$filter_by_profile) {
            $keys = $this->getClient()->keys(self::key("tasks:*:$filter_by_status"));
        } elseif (!$filter_by_status) {
            $keys = $this->getClient()->keys(self::key("tasks:$filter_by_profile:*"));
        } else {
            $keys = $this->getClient()->keys(self::key("tasks:$filter_by_profile:$filter_by_status"));
        }

        // Force profile order
        sort($keys);

        // Set an order for status that will affect how Tasks are listed
        $status_rank = [
            Task::STATUS_FAILED   => -1,
            Task::STATUS_PENDING  => 0,
            Task::STATUS_WAITING  => 1,
            Task::STATUS_RUNNING  => 2,
            Task::STATUS_SUCCESS  => 3,
            Task::STATUS_FINISHED => 4,
        ];

        // List all Tasks from all selected keys keeping Redis list's ranks
        $tasks = [];
        foreach ($keys as $key) {
            $task_ids = $this->getClient()->lrange($key, 0, -1);
            foreach ($task_ids as $task_id) {
                if (!$task = $this->getTask($task_id)) {
                    continue;
                }

                $status = $status_rank[$task->getStatus()];
                if (!isset($tasks[$status])) {
                    $tasks[$status] = [];
                }

                $profile = $task->getProfile();
                if (!isset($tasks[$status][$profile])) {
                    $tasks[$status][$profile] = [];
                }

                $tasks[$status][$profile][$task_id] = $task;
            }
        }

        // Order by status
        sort($tasks);

        // Flatten the list of Tasks
        // This will also flatten positions across the different Redis keys
        $list = [];
        foreach ($tasks as $status) {
            foreach ($status as $profile) {
                foreach ($profile as $task) {
                    $list[] = $task;
                }
            }
        }

        // Re order tasks with their position in the Redis list
        if ('asc' === $sort_by_order) {
            ksort($list);
        } else {
            krsort($list);
        }

        return $list;
    }

    /**
     *
     * @param int $id
     * @return Task|null
     */
    public function getTask($id)
    {
        if (!$id) {
            return null;
        }

        if ($id instanceof Task and !$id = $id->getId()) {
            return null;
        }

        return Task::build($this->getClient()->get(self::key("task:$id")));
    }

    /**
     *
     * @param Task $task
     * @param bool $human_readable
     * @return float|string
     */
    public function getProgress(Task $task, $human_readable = true)
    {
        $id = $task instanceof Task ? $task->getId() : $task;

        if (!$task = $this->getTask($id)) {
            return 0;
        }

        // recursively add children's progressions
        $progress = $task->getProgress();
        foreach ($task->getChildren() as $child) /* @var $child Task */
        {
            $progress += $this->getProgress($child, false);
        }

        $global = $progress / ($task->countChildren() + 1);

        return $human_readable ? round($global * 100) . '%' : $global;
    }

    /**
     * Generates a `key` based on the function arguments
     *
     * @return string
     */
    protected static function key()
    {
        $portions = array_merge([self::PREFIX], func_get_args());

        return implode(':', $portions);
    }
}
