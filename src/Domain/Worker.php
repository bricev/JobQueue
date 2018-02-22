<?php

namespace JobQueue\Domain;

use JobQueue\Domain\Task\Profile;
use JobQueue\Domain\Task\Queue;
use JobQueue\Domain\Task\Status;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;

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
    public function __construct($name, Queue $queue, Profile $profile)
    {
        $this->name = $name;
        $this->queue = $queue;
        $this->profile = $profile;
    }

    /**
     * Fetch tasks from queue and perform their affiliated job
     *
     */
    public function run(): void
    {
        $this->log('Worker starts running', [
            'worker' => $this->name,
            'profile' => (string) $this->profile
        ]);

        while ($task = $this->queue->fetch($this->profile)) {
            // Set up the job
            $job = $task->getJob();
            if ($this->logger) {
                $job->setLogger($this->logger);
            }

            $this->log('Worker execute a task', [
                'task' => (string) $task->getIdentifier(),
                'job' => (string) $task->getJobName()
            ]);

            try {
                $job->setUp($task);
                $job->perform($task);
                $job->tearDown($task);

            } catch (\Exception $e) {
                $this->queue->updateStatus($task, new Status(Status::FAILED));

                $this->log('Worker failed to execute the job', [
                    'task' => (string) $task->getIdentifier(),
                    'job' => (string) $task->getJobName()
                ]);
            }

            $this->queue->updateStatus($task, new Status(Status::FINISHED));
        }

    }

    /**
     *
     * @param string $message
     * @param array  $context
     * @param string $level
     */
    private function log(string $message, array $context = [], string $level = LogLevel::INFO): void {
        if (!$this->logger) {
            return;
        }

        $this->logger->log($level, $message, $context);
    }
}
