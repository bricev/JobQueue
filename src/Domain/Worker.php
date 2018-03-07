<?php

namespace JobQueue\Domain;

use JobQueue\Domain\Job\ExecutableJob;
use JobQueue\Domain\Task\Profile;
use JobQueue\Domain\Task\Queue;
use JobQueue\Domain\Task\Status;
use JobQueue\Domain\Task\Task;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

final class Worker implements LoggerAwareInterface
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
     * @var LoggerInterface
     */
    protected $logger;

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
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        if ($logger instanceof \Monolog\Logger) {
            $profile = $this->getProfile();
            $logger->pushProcessor(function ($record) use ($profile) {
                $record['extra']['profile'] = $profile;
                return $record;
            });
        }

        $this->logger = $logger;
    }

    /**
     *
     * @param int|null $quantity
     */
    public function consume(int $quantity = null)
    {
        $i = 0;
        while ($task = $this->queue->fetch($this->profile)) {
            // Configure the job
            $job = $this->getAndConfigureJob($task);

            try {
                // Execute the job
                $job->setUp($task);
                $job->perform($task);
                $job->tearDown($task);

                $this->queue->updateStatus($task, new Status(Status::FINISHED));

            } catch (\Exception $e) {
                // Report error to logger if exists
                if ($this->logger) {
                    $this->logger->error($e->getMessage(), [
                        'worker' => $this->getName(),
                        'profile' => (string) $task->getProfile(),
                        'job' => $task->getJobName(true),
                    ]);
                }

                // Mark task as failed
                $this->queue->updateStatus($task, new Status(Status::FAILED));
            }

            // Exit worker if a quantity has been set
            $i++;
            if ($quantity === $i) {
                break;
            }
        }
    }

    /**
     *
     * @param Task $task
     * @return ExecutableJob
     */
    private function getAndConfigureJob(Task $task): ExecutableJob
    {
        $job = $task->getJob();

        $logger = $this->logger;

        if ($logger instanceof \Monolog\Logger) {
            $logger->pushProcessor(function ($record) use ($task) {
                $record['extra']['job'] = $task->getJobName(true);
                return $record;
            });
        }

        if (!is_null($logger)) {
            $job->setLogger($logger);
        }

        return $job;
    }
}
