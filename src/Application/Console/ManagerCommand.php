<?php

namespace JobQueue\Application\Console;

use JobQueue\Domain\Task\Queue;
use Symfony\Component\Console\Command\Command;

abstract class ManagerCommand extends Command
{
    /**
     *
     * @var Queue
     */
    protected $queue;

    /**
     *
     * @param Queue  $queue
     * @param string $name
     */
    public function __construct(Queue $queue, string $name = null)
    {
        $this->queue = $queue;

        parent::__construct($name);
    }
}
