<?php

namespace JobQueue\Domain;

use JobQueue\Domain\Task\Profile;
use JobQueue\Domain\Task\Queue;
use JobQueue\Domain\Task\Status;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

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
     * @param         $name
     * @param Queue   $queue
     * @param Profile $profile
     */
    public function __construct(string $name, Queue $queue, Profile $profile)
    {
        $this->name = $name;
        $this->queue = $queue;
        $this->profile = $profile;
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
     * @param int|null $quantity
     */
    public function consume(int $quantity = null)
    {
        $i = 0;
        while ($task = $this->queue->fetch($this->profile)) {
            // Set up the job
            $job = $task->getJob();
            if ($this->logger) {
                $job->setLogger($this->logger);
            }

            try {
                // Execute the job
                $job->setUp($task);
                $job->perform($task);
                $job->tearDown($task);

                $this->queue->updateStatus($task, new Status(Status::FINISHED));

            } catch (\Exception $e) {
                $this->queue->updateStatus($task, new Status(Status::FAILED));
            }

            $i++;

            if ($quantity === $i) {
                break;
            }
        }
    }
}
