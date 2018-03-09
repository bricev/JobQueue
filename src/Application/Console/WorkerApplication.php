<?php

namespace JobQueue\Application\Console;

use JobQueue\Domain\Task\Queue;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Application;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class WorkerApplication extends Application implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     *
     * @param Queue                    $queue
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(Queue $queue, EventDispatcherInterface $eventDispatcher)
    {
        parent::__construct('worker', 1.0);

        $consumeCommand = new Consume($queue, $eventDispatcher);
        if ($this->logger) {
            $consumeCommand->setLogger($this->logger);
        }

        $this->add($consumeCommand);
        $this->setDefaultCommand('consume', true);
    }
}
