<?php

namespace JobQueue\Application\Console;

use JobQueue\Domain\Task\Queue;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class WorkerApplication extends Application
{
    /**
     *
     * @param Queue                    $queue
     * @param EventDispatcherInterface $eventDispatcher
     * @param LoggerInterface|null     $logger
     */
    public function __construct(Queue $queue, EventDispatcherInterface $eventDispatcher, LoggerInterface $logger = null)
    {
        parent::__construct('worker', 1.0);

        $consumeCommand = new Consume($queue, $eventDispatcher);
        if ($logger) {
            $consumeCommand->setLogger($logger);
        }

        $this->add($consumeCommand);
        $this->setDefaultCommand('consume', true);
    }
}
