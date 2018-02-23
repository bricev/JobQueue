<?php

namespace JobQueue\Domain;

use JobQueue\Domain\Task\Profile;
use JobQueue\Domain\Task\Queue;
use JobQueue\Domain\Task\Status;
use Psr\Log\LoggerInterface;

final class Worker
{
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
     * @param         $name
     * @param Queue   $queue
     * @param Profile $profile
     */
    public function __construct($name, Queue $queue, Profile $profile)
    {
        $this->name = $name;
        $this->queue = $queue;
        $this->profile = $profile;
    }

    /**
     *
     * @param LoggerInterface|null $logger
     */
    public function run(LoggerInterface $logger = null): void
    {
        while ($task = $this->queue->fetch($this->profile)) {
            // Set up the job
            $job = $task->getJob();
            if ($logger) {
                $job->setLogger($logger);
            }

            try {
                $job->setUp($task);
                $job->perform($task);
                $job->tearDown($task);

            } catch (\Exception $e) {
                $this->queue->updateStatus($task, new Status(Status::FAILED));
            }

            $this->queue->updateStatus($task, new Status(Status::FINISHED));
        }
    }
}
