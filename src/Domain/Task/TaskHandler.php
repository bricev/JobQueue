<?php

namespace JobQueue\Domain\Task;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class TaskHandler implements EventSubscriberInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     *
     * @var Queue
     */
    private $queue;

    /**
     *
     * @var EventDispatcher
     */
    private $dispatcher;

    /**
     *
     * @param Queue           $queue
     * @param EventDispatcher $dispatcher
     */
    public function __construct(Queue $queue, EventDispatcher $dispatcher)
    {
        $this->queue = $queue;
        $this->dispatcher = $dispatcher;
    }

    /**
     *
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            TaskWasFetched::NAME  => [
                ['onTaskFetchedConfigureJob', 100],
                ['onTaskFetchedExecuteJob', -100],
            ],
            TaskWasExecuted::NAME => 'onTaskExecuted',
            TaskHasFailed::NAME   => 'onTaskFailed',
        ];
    }

    /**
     *
     * @param TaskWasFetched $event
     */
    public function onTaskFetchedConfigureJob(TaskWasFetched $event)
    {
        $task = $event->getTask();
        $job = $task->getJob();

        // Configure the job
        if ($this->logger) {
            $job->setLogger($this->logger);
        }
    }

    /**
     *
     * @param TaskWasFetched $event
     */
    public function onTaskFetchedExecuteJob(TaskWasFetched $event)
    {
        $task = $event->getTask();
        $job = $task->getJob();

        try {
            // Execute the job
            $job->setUp($task);
            $job->perform($task);
            $job->tearDown($task);

            $this->dispatcher->dispatch(TaskWasExecuted::NAME, new TaskWasExecuted($task));

        } catch (\Exception $e) {
            $this->dispatcher->dispatch(TaskHasFailed::NAME, new TaskHasFailed($task));
        }
    }

    /**
     *
     * @param TaskWasExecuted $event
     */
    public function onTaskExecuted(TaskWasExecuted $event)
    {
        $this->queue->updateStatus($event->getTask(), new Status(Status::FINISHED));
    }

    /**
     *
     * @param TaskHasFailed $event
     */
    public function onTaskFailed(TaskHasFailed $event)
    {
        $this->queue->updateStatus($event->getTask(), new Status(Status::FAILED));
    }
}
