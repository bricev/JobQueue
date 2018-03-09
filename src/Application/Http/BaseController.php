<?php

namespace JobQueue\Application\Http;

use JobQueue\Domain\Task\Queue;
use Psr\Http\Server\RequestHandlerInterface;

abstract class BaseController implements RequestHandlerInterface
{
    /**
     *
     * @var Queue
     */
    protected $queue;

    /**
     *
     * @param Queue $queue
     */
    public function __construct(Queue $queue)
    {
        $this->queue = $queue;
    }
}
