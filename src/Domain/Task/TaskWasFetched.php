<?php

namespace JobQueue\Domain\Task;

use Symfony\Component\EventDispatcher\Event;

final class TaskWasFetched extends Event
{
    const NAME = 'task.fetched';

    /**
     *
     * @var Task
     */
    private $task;

    /**
     *
     * @param Task  $task
     */
    public function __construct(Task $task)
    {
        $this->task = $task;
    }

    /**
     *
     * @return Task
     */
    public function getTask(): Task
    {
        return $this->task;
    }
}
