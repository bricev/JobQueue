<?php

namespace JobQueue\Domain\Task;

use JobQueue\Domain\Worker\Worker;
use Symfony\Component\EventDispatcher\Event;

final class WorkerWasStarted extends Event
{
    const NAME = 'worker.started';

    /**
     *
     * @var Worker
     */
    private $worker;

    /**
     *
     * @param Task   $task
     * @param Worker $worker
     */
    public function __construct(Worker $worker)
    {
        $this->worker = $worker;
    }

    /**
     *
     * @return Worker
     */
    public function getWorker(): Worker
    {
        return $this->worker;
    }
}
