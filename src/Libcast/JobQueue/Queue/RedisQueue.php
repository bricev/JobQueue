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
     * @param  Task $task
     * @param  string $position head|tail
     * @return Task
     */
    protected function insert(Task $task, $position = 'tail')
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

        // enqueue Task in $position (head or tail) of the sorted list
        $push = 'tail' === $position ? 'rpush' : 'lpush';
        $this->getClient()->$push(
            self::key('tasks', $task->getProfile(), $task->getStatus()),
            $task->getId()
        );

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
     * @return Task
     */
    public function shift(Task $task)
    {
        return $this->insert($task, 'tail');
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
                    $parent->setStatus(Task::STATUS_FAILED);
                    $this->update($parent);
                    break;
            }
        }

        // Persist data
        $this->getClient()->set(
            self::key('task', $task->getId()),
            json_encode($task)
        );
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
                return !preg_match('/^'. self::PREFIX .':tasks:([^:]+):'. Task::STATUS_FINISHED .'$/', $value);
            });
        } elseif (!$filter_by_profile) {
            $keys = $this->getClient()->keys(self::key("tasks:*:$filter_by_status"));
        } elseif (!$filter_by_status) {
            $keys = $this->getClient()->keys(self::key("tasks:$filter_by_profile:*"));
        } else {
            $keys = $this->getClient()->keys(self::key("tasks:$filter_by_profile:$filter_by_status"));
        }

        // Getting all Task ids from the filtered lists
        $tasks = [];
        foreach ($keys as $key) {
            $ids = $this->getClient()->lrange($key, 0, -1);
            foreach ($ids as $id) {
                $task = $this->getTask($id);
                $tasks[$task->getId()] = $task;
            }
        }

        // Re order tasks
        if ('asc' === $sort_by_order) {
            ksort($tasks);
        } else {
            krsort($tasks);
        }

        return $tasks;
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
