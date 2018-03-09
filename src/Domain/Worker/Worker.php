<?php

namespace JobQueue\Domain\Worker;

use JobQueue\Domain\Task\Profile;
use JobQueue\Domain\Task\Queue;
use JobQueue\Domain\Task\TaskHandler;
use JobQueue\Domain\Task\TaskWasFetched;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     *
     * @param string                   $name
     * @param Queue                    $queue
     * @param Profile                  $profile
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(string $name, Queue $queue, Profile $profile, EventDispatcherInterface $eventDispatcher)
    {
        $this->name = $name;
        $this->queue = $queue;
        $this->profile = $profile;
        $this->eventDispatcher = $eventDispatcher;
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
        $this->eventDispatcher->dispatch(WorkerWasStarted::NAME, new WorkerWasStarted($this));

        // Set up the Task handler which subscribe to tasks domain events
        $taskHandler = new TaskHandler($this->queue, $this->eventDispatcher);
        if ($this->logger) {
            $taskHandler->setLogger($this->logger);
        }
        $this->eventDispatcher->addSubscriber($taskHandler);

        $i = 0;
        while ($task = $this->queue->fetch($this->profile)) {
            $this->eventDispatcher->dispatch(TaskWasFetched::NAME, new TaskWasFetched($task));

            // Exit worker if a quantity has been set
            $i++;
            if ($quantity === $i) {
                break;
            }
        }

        $this->eventDispatcher->dispatch(WorkerHasFinished::NAME, new WorkerHasFinished($this));
    }
}
