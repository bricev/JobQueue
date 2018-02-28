<?php

namespace JobQueue\Domain\Job;

use JobQueue\Domain\Task\Task;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;

/**
 *
 * @method void emergency($message, array $context = [])
 * @method void alert($message, array $context = [])
 * @method void critical($message, array $context = [])
 * @method void error($message, array $context = [])
 * @method void warning($message, array $context = [])
 * @method void notice($message, array $context = [])
 * @method void info($message, array $context = [])
 * @method void debug($message, array $context = [])
 */
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

    /**
     * Magic method for "log-level methods"
     * Eg. `this->alert($message)`
     *
     * @param $name
     * @param $arguments
     */
    public function __call($name, $arguments)
    {
        $reflection = new \ReflectionClass(LogLevel::class);

        if (!in_array($name, $reflection->getConstants())) {
            throw new \RuntimeException(sprintf('Method "%s" can\'t be called on the job logger'));
        }

        $message = array_shift($arguments);
        $context = empty($arguments) ? [] : array_shift($arguments);

        $this->logger->$name($message, $context);
    }
}
