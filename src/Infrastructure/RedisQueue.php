<?php

namespace JobQueue\Infrastructure;

use JobQueue\Domain\Task\Profile;
use JobQueue\Domain\Task\Queue;
use JobQueue\Domain\Task\Status;
use JobQueue\Domain\Task\Task;
use Predis\Client;
use Predis\Collection\Iterator\Keyspace;

/**
 * Redis implementation of the task queue.
 *
 * Used keys:
 * - jobqueue.tasks.{task_identifier}
 * - jobqueue.{profile}.waiting
 * - jobqueue.{profile}.running
 * - jobqueue.{profile}.failed
 */
final class RedisQueue implements Queue
{
    /**
     *
     * @var Client
     */
    private $predis;

    /**
     *
     * @param Client $predis
     */
    public function __construct(Client $predis)
    {
        $this->predis = $predis;
    }

    /**
     *
     * @param Task $task
     */
    public function add(Task $task): void
    {
        $this->predis->set($this->getKey($task), serialize($task));
        $this->predis->lpush($this->getTaskList($task), $task->getIdentifier());
    }

    /**
     *
     * @param Profile $profile
     * @return Task
     */
    public function fetch(Profile $profile): Task
    {
        try {
            $identifier = $this->predis->brpoplpush(
                $this->getList($profile, new Status(Status::WAITING)),
                $this->getList($profile, new Status(Status::RUNNING)),
                300 // 5 minutes
            );

            // Find and update task
            $task = $this->find($identifier);
            $task->updateStatus(new Status(Status::RUNNING));
            $this->predis->set($this->getKey($task), serialize($task));

            return $task;

        } catch (\Exception $e) {
            // sleep a little to avoid high CPU consuming infinite loops...
            sleep(3);

            // ... and try again
            return $this->fetch($profile);
        }
    }

    /**
     *
     * @param string $identifier
     * @return Task
     */
    public function find(string $identifier): Task
    {
        if (!$serializedTask = $this->predis->get($this->getKey($identifier))) {
            throw new \RuntimeException(sprintf('Task %s does not exists', $identifier));
        }

        return unserialize($serializedTask);
    }

    /**
     *
     * @param Task   $task
     * @param Status $status
     */
    public function updateStatus(Task $task, Status $status): void
    {
        if ((string) $status === $task->getStatus()) {
            return;
        }

/**
 * Can be useful to mark tasks as success after a crash
 * Required now as the worker needs to mark tasks as finished
 */
//        if (Status::RUNNING === (string) $task->getStatus()) {
//            throw new \RuntimeException('The status of a running task can\'t be updated');
//        }

        if (Status::RUNNING === (string) $status) {
            throw new \RuntimeException('A task can not be marked as "running"');
        }

        // Remove task from its current list (there is no `finished` list)
        if (Status::FINISHED !== (string) $task->getStatus()) {
            $this->predis->lrem($this->getTaskList($task), 0, (string) $task);
        }

        // Insert the task to its new list (except for `finished` tasks)
        if (Status::FINISHED !== (string) $status) {
            $this->predis->lpush($this->getList($task->getProfile(), $status), (string) $task);
        }

        // Update task
        $task->updateStatus($status);
        $this->predis->set($this->getKey($task), serialize($task));
    }

    /**
     *
     * @param Profile $profile
     * @param Status  $status
     * @param string  $orderBy
     * @return Task[]
     * @throws \Exception
     */
    public function dump(Profile $profile = null, Status $status = null, string $orderBy = 'date'): array
    {
        if (!in_array($orderBy, ['date', 'profile', 'status'])) {
            throw new \Exception(sprintf('Impossible to order by "%s"', $orderBy));
        }

        // List all tasks
        $tasks = [];
        foreach (new Keyspace($this->predis, $this->getKey()) as $key) {
            $task = unserialize($this->predis->get($key)); /** @var Task $task */

            if ($profile and (string) $profile !== (string) $task->getProfile()) {
                continue;
            }

            if ($status and (string) $status !== (string) $task->getStatus()) {
                continue;
            }

            $tasks[] = [
                'date' => $task->getCreatedAt(),
                'profile' => (string) $task->getProfile(),
                'status' => array_search((string) $task->getStatus(), Status::listStatus()),
                'task' => $task,
            ];
        }

        // Order Tasks
        uasort($tasks, function ($a, $b) use ($orderBy) {
            $aValue = $a[$orderBy];
            $bValue = $b[$orderBy];

            if ($aValue === $bValue) return 0;

            return $aValue < $bValue ? -1 : 1;
        });

        // Clean return
        foreach ($tasks as $key => $task) {
            $tasks[$key] = $task['task'];
        }

        return $tasks;
    }

    public function flush(): void
    {
        $pipe = $this->predis->pipeline();

        foreach ($this->predis->keys('jobqueue.*') as $key) {
            $pipe->del($key);
        }

        $pipe->execute();
    }

    public function restore(): void
    {
        $tasks = $this->dump();

        $this->flush();

        foreach ($tasks as $task) {
            $task->updateStatus(new Status(Status::WAITING));
            $this->add($task);
        }
    }

    /**
     *
     * @param Profile $profile
     * @param Status  $status
     * @return string
     */
    protected function getList(Profile $profile = null, Status $status = null): string
    {
        return sprintf('jobqueue.%s.%s', $profile ?: '*', $status ?: '*');
    }

    /**
     *
     * @param Task $task
     * @return string
     */
    protected function getTaskList(Task $task): string
    {
        return $this->getList($task->getProfile(), $task->getStatus());
    }

    /**
     *
     * @param $identifier
     * @return string
     */
    protected function getKey($identifier = null): string
    {
        if ($identifier instanceof Task) {
            $identifier = (string) $identifier->getIdentifier();
        }

        return sprintf('jobqueue.tasks.%s', $identifier ?: '*');
    }
}