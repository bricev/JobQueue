<?php

namespace JobQueue\Domain\Task;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
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
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**

     * @param Queue                    $queue
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(Queue $queue, EventDispatcherInterface $eventDispatcher)
    {
        $this->queue = $queue;
        $this->eventDispatcher = $eventDispatcher;
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

            $this->eventDispatcher->dispatch(TaskWasExecuted::NAME, new TaskWasExecuted($task));

        } catch (\Exception $e) {
            $this->eventDispatcher->dispatch(TaskHasFailed::NAME, new TaskHasFailed($task));

            if ($this->logger) {
                $this->logger->error($e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
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
