<?php

namespace JobQueue\Domain\Worker;

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
