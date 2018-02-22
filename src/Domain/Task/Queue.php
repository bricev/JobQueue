<?php

namespace JobQueue\Domain\Task;

interface Queue
{
    /**
     * Adds a task to the queue
     *
     * @param Task $task
     */
    public function add(Task $task): void;

    /**
     * Fetches a task from the queue (FIFO)
     *
     * @param Profile $profile
     * @return Task
     */
    public function fetch(Profile $profile): Task;

    /**
     * Finds a task with its identifier
     *
     * @param string $identifier
     * @return Task
     */
    public function find(string $identifier): Task;

    /**
     * Updates a task's status
     *
     * @param Task   $task
     * @param Status $status
     */
    public function updateStatus(Task $task, Status $status): void;

    /**
     * Lists tasks
     *
     * @param Profile $profile
     * @param Status  $status
     * @param string  $orderBy
     * @return Task[]
     */
    public function dump(Profile $profile = null, Status $status = null, string $orderBy = 'status'): array;

    /**
     * Deletes all tasks
     *
     */
    public function flush(): void;

    /**
     * Sets all tasks to `waiting` status
     *
     */
    public function restore(): void;
}