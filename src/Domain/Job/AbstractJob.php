<?php

namespace JobQueue\Domain\Job;

use JobQueue\Domain\Task\Task;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;

abstract class AbstractJob implements ExecutableJob
{
    use LoggerAwareTrait;

    /**
     *
     * @param Task $task
     */
    public function setUp(Task $task) {}

    /**
     *
     * @param Task $task
     */
    abstract public function perform(Task $task);

    /**
     *
     * @param Task $task
     */
    public function tearDown(Task $task) {}

    /**
     *
     * @param string $message
     * @param array  $context
     * @param string $level
     */
    protected function log(string $message, array $context = [], string $level = LogLevel::INFO)
    {
        if (!$this->logger) {
            return;
        }

        $this->logger->log($level, $message, $context);
    }
}
