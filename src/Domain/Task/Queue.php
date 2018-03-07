<?php

namespace JobQueue\Domain\Task;

interface Queue
{
    /**
     * Adds a task to the queue
     *
     * @param Task[] ...$tasks
     */
    public function add(Task ...$tasks);

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
    public function updateStatus(Task $task, Status $status);

    /**
     * Lists tasks according to criteria
     *
     * @param Profile $profile
     * @param Status  $status
     * @param array   $tags
     * @param string  $orderBy
     * @return Task[]
     */
    public function search(Profile $profile = null, Status $status = null, array $tags = [], string $orderBy = 'status'): array;

    /**
     *
     * @param string $identifier
     */
    public function delete(string $identifier);

    /**
     * Deletes all tasks
     *
     */
    public function flush();

    /**
     * Sets all tasks to `waiting` status
     *
     */
    public function restore();
}
