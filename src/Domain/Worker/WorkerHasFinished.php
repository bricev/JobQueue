<?php

namespace JobQueue\Domain\Worker;

use Symfony\Component\EventDispatcher\Event;

final class WorkerHasFinished extends Event
{
    const NAME = 'worker.finished';

    /**
     *
     * @var Worker
     */
    private $worker;

    /**
     *
     * @param \JobQueue\Domain\Worker\Worker $worker
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
