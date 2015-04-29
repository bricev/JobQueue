<?php

/*
 * This file is part of Libcast JobQueue component.
 *
 * (c) Brice Vercoustre <brcvrcstr@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Libcast\JobQueue\Job;

use Libcast\JobQueue\Exception\JobException;
use Libcast\JobQueue\Queue\QueueInterface;
use Libcast\JobQueue\Task;

/**
 *
 * @method perform
 */
abstract class AbstractJob
{
    /**
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     *
     * @var array
     */
    protected $parameters = [];

    /**
     *
     * @var \Libcast\JobQueue\Queue\QueueInterface
     */
    protected $queue;

    /**
     *
     * @var \Libcast\JobQueue\Task
     */
    protected $task;

    /**
     *
     * @param Task $task
     * @param QueueInterface $queue
     * @param \Psr\Log\LoggerInterface $logger
     */
    function __construct(Task $task = null, QueueInterface $queue = null, \Psr\Log\LoggerInterface $logger = null)
    {
        $this->task = $task;
        $this->queue = $queue;
        $this->logger = $logger;

        if ($task instanceof Task) {
            $this->setParameters($task->getParameters());
        }
    }

    /**
     *
     * @return QueueInterface
     */
    protected function getQueue()
    {
        return $this->queue;
    }

    /**
     *
     * @return Task
     */
    protected function getTask()
    {
        return $this->task;
    }

    /**
     *
     * @param array $parameters
     * @throws JobException
     */
    protected function setParameters(array $parameters)
    {
        $this->parameters = array_merge($this->parameters, $parameters);
    }

    /**
     *
     * @return array
     */
    protected function getParameters()
    {
        return $this->parameters;
    }

    /**
     *
     * @param $key
     * @return bool
     */
    protected function hasParameter($key)
    {
        return in_array($key, array_keys($this->getParameters()));
    }

    /**
     *
     * @param $key
     * @param null $default
     * @param bool $strict
     * @return null
     */
    protected function getParameter($key, $default = null, $strict = true)
    {
        if ($this->hasParameter($key)) {
            return $this->parameters[$key];
        }

        if ($strict and is_null($default)) {
            throw new JobException("Missing '$key' parameter");
        }

        return $default;
    }

    /**
     * Sets a PSR valid logger
     *
     * @param   \Psr\Log\LoggerInterface  $logger
     */
    protected function setLogger(\Psr\Log\LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * 
     * @return \Psr\Log\LoggerInterface
     */
    protected function getLogger()
    {
        return $this->logger;
    }

    /**
     * Log message only if a logger has been set
     *
     * @param   string  $message
     * @param   mixed   $context
     * @param   string  $level    info|warning|error|debug
     */
    protected function log($message, $context = [], $level = 'info')
    {
        if ($logger = $this->getLogger()) {
            $logger->$level($message, (array) $context);
        }
    }

    /**
     *
     * @return bool
     */
    public function setup()
    {
        return true;
    }

    /**
     *
     * @return bool
     */
    public function terminate()
    {
        return true;
    }

    /**
     *
     * @return bool
     * @throws JobException
     */
    public function execute()
    {
        $exception = null;

        try {
            switch (false) {
                case $this->setup():        $action = 'setup';
                case $this->perform():      $action = isset($action) ? $action : 'perform';
                case $this->terminate():    $action = isset($action) ? $action : 'terminate';

                    throw new JobException("Impossible to $action the Job");
            }
        } catch (\Exception $e) {
            if ($this->getTask()->canFail()) {
                // If Task can fail, then silently log the error...
                $this->log('Silenced Job failure', [
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine(),
                ], 'error');
            } else {
                // ... otherwise re-throw the exception
                $exception = $e;
            }
        }

        if ($exception instanceof \Exception) {
            throw new \Exception($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * Update Task's progress and persist data
     *
     * @param   float $percent
     * @throws  \Libcast\JobQueue\Exception\JobException
     */
    protected function setTaskProgress($percent)
    {
        if (!$queue = $this->getQueue()) {
            throw new JobException('There is no Queue to update Task progress');
        }

        if (!$task = $this->getTask()) {
            throw new JobException('There is no Task to set progress to');
        }

        $task->setProgress($percent);
        $queue->update($task);
    }

    public function __toString()
    {
        return get_class($this);
    }
}
