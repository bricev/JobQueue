<?php

namespace JobQueue\Domain\Worker;

use JobQueue\Domain\Task\Profile;
use JobQueue\Domain\Task\Queue;
use JobQueue\Domain\Task\TaskHandler;
use JobQueue\Domain\Task\TaskWasFetched;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class Worker implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     *
     * @var string
     */
    private $name;

    /**
     *
     * @var Queue
     */
    private $queue;

    /**
     *
     * @var Profile
     */
    private $profile;

    /**
     *
     * @var EventDispatcher
     */
    protected $dispatcher;

    /**
     *
     * @param string          $name
     * @param Queue           $queue
     * @param Profile         $profile
     * @param EventDispatcher $dispatcher
     */
    public function __construct(string $name, Queue $queue, Profile $profile, EventDispatcher $dispatcher)
    {
        $this->name = $name;
        $this->queue = $queue;
        $this->profile = $profile;
        $this->dispatcher = $dispatcher;
    }

    /**
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     *
     * @return Profile
     */
    public function getProfile(): Profile
    {
        return $this->profile;
    }

    /**
     *
     * @param int|null $quantity Number of tasks to handle
     */
    public function consume(int $quantity = null)
    {
        $this->dispatcher->dispatch(WorkerWasStarted::NAME, new WorkerWasStarted($this));

        // Set up the Task handler which subscribe to tasks domain events
        $taskHandler = new TaskHandler($this->queue, $this->dispatcher);
        if ($this->logger) {
            $taskHandler->setLogger($this->logger);
        }
        $this->dispatcher->addSubscriber($taskHandler);

        $i = 0;
        while ($task = $this->queue->fetch($this->profile)) {
            $this->dispatcher->dispatch(TaskWasFetched::NAME, new TaskWasFetched($task));

            // Exit worker if a quantity has been set
            $i++;
            if ($quantity === $i) {
                break;
            }
        }

        $this->dispatcher->dispatch(WorkerHasFinished::NAME, new WorkerHasFinished($this));
    }
}
