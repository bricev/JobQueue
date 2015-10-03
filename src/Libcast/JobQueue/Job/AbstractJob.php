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

use Doctrine\Common\Cache\Cache;
use Libcast\JobQueue\Exception\JobException;
use Libcast\JobQueue\LoggerTrait;
use Libcast\JobQueue\Queue\QueueInterface;
use Libcast\JobQueue\Task;
use Libcast\JobQueue\Worker;

/**
 *
 * @method perform
 */
abstract class AbstractJob
{
    use LoggerTrait;

    /**
     *
     * @var \Libcast\JobQueue\Task
     */
    protected $task;

    /**
     *
     * @var \Libcast\JobQueue\Worker
     */
    protected $worker;

    /**
     *
     * @var \Libcast\JobQueue\Queue\QueueInterface
     */
    protected $queue;

    /**
     *
     * @var \Doctrine\Common\Cache\Cache
     */
    protected $cache;

    /**
     *
     * @var array
     */
    protected $parameters = [];

    /**
     *
     * @param Task $task
     * @param QueueInterface $queue
     * @param \Psr\Log\LoggerInterface $logger
     */
    function __construct(Task $task = null, Worker $worker = null, QueueInterface $queue = null, Cache $cache = null, \Psr\Log\LoggerInterface $logger = null)
    {
        $this->setLogger($logger, $worker, $task);

        $this->task = $task;
        $this->worker = $worker;
        $this->queue = $queue;
        $this->cache = $cache;

        if ($task instanceof Task) {
            $this->setParameters($task->getParameters());
        }
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
     * @return Worker
     */
    protected function getWorker()
    {
        return $this->worker;
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
     * @return Cache
     */
    public function getCache()
    {
        return $this->cache;
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
        // Set worker name for better Job tracking
        $this->getTask()->setWorkerName((string) $this->getWorker());

        $exception = null;

        try {
            if (!$this->setup()) {
                throw new JobException('Impossible to setup the Job');
            } elseif (!$this->perform()) {
                throw new JobException('Impossible to perform the Job');
            } elseif (!$this->terminate()) {
                throw new JobException('Impossible to terminate the Job');
            }
        } catch (\Exception $e) {
            if ($this->getTask()->canFail()) {
                // If Task can fail, then silently log the error...
                $this->warning('Silenced Job failure', [
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine(),
                ]);
            } else {
                // ... otherwise re-throw the exception
                $exception = $e;
            }
        }

        if ($exception instanceof \Exception) {
            throw new JobException($exception->getMessage(), $exception->getCode(), $exception);
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
