<?php

/*
 * This file is part of Libcast JobQueue component.
 *
 * (c) Brice Vercoustre <brcvrcstr@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Libcast\JobQueue;

use Doctrine\Common\Cache\Cache;
use Libcast\JobQueue\Queue\QueueInterface;

class Worker
{
    use LoggerTrait;

    /**
     *
     * @var string
     */
    protected $name;

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
     * @var string
     */
    protected $profile;

    /**
     *
     * @var int Unix timestamp
     */
    protected $started_at;

    /**
     * Setup a Worker to connect the Queue.
     * The Worker will receive Tasks from Queue profiled sets.
     * Each Task will setup a Job that can be run (executed).
     *
     * @param                               $profile
     * @param QueueInterface                $queue
     * @param Cache|null                    $cache
     * @param \Psr\Log\LoggerInterface|null $logger
     */
    public function __construct($profile, QueueInterface $queue, Cache $cache = null, \Psr\Log\LoggerInterface $logger = null)
    {
        $this->profile = $profile;
        $this->queue = $queue;
        $this->cache = $cache;
        $this->started_at = time();

        if ($logger) {
            $this->setLogger($logger, $this);
        }

        $this->info('Worker started');
    }

    /**
     *
     * @return string
     */
    public function getName()
    {
        if ($this->name) {
            return $this->name;
        }

        return $this->name = sprintf('JobQueue:%s:%s @%s',
            gethostname(),
            $this->getProfile(),
            $this->getStartedAt('y-m-d H:i:s'));
    }

    /**
     *
     * @return string
     */
    public function getProfile()
    {
        return $this->profile;
    }

    /**
     *
     * @return \Libcast\JobQueue\Queue\QueueInterface
     */
    protected function getQueue()
    {
        return $this->queue;
    }

    /**
     *
     * @return Cache
     */
    protected function getCache()
    {
        return $this->cache;
    }

    /**
     *
     * @return int
     */
    public function getStartedAt($format = 'U')
    {
        return date($format, $this->started_at);
    }

    /**
     * Execute Tasks's jobs
     */
    public function run()
    {
        $queue = $this->getQueue(); /* @var $queue \Libcast\JobQueue\Queue\RedisQueue */
        $cache = $this->getCache();

        while ($task = $queue->fetch($this->getProfile())) { /* @var $task \Libcast\JobQueue\Task */
            // Set the logger for better Task tracking
            $this->setLoggerTask($task);

            try {
                // get Task Id (this should throw an exception in case $task is not a Task)
                $task_id = $task->getId();
                $this->info('New Task', $task->getParameters(), [
                    'job_class' => (string) $task->getJob(),
                ]);

                // Get the Job from the Task
                $class = (string) $task->getJob();
                $job = new $class($task, $this, $queue, $cache, $this->getLogger()); /* @var $job \Libcast\JobQueue\Job\JobInterface */

                // Run Job
                // This will throw exceptions in case of failure
                $job->execute();

                $this->info('Task successfully executed');

                // If no child, the Task will be marked as finished
                $finished = true;

                if ($children = $task->getChildren()) {
                    foreach ($children as $child) { /* @var $child \Libcast\JobQueue\Task */
                        $child->setRootId($task->getRootId());
                        $child->setParentId($task->getId());
                        $queue->shift($child, $task);

                        // There is at least one child: the Task is not finished
                        $finished = false;
                    }
                }

                if ($finished) {
                    // No child: the Task is finished
                    $task->setStatus(Task::STATUS_FINISHED);
                    $queue->update($task);
                } else {
                    // There is some work to be done with Task's children
                    // mark Task as success (not yet finished)
                    $task->setStatus(Task::STATUS_SUCCESS);
                    $queue->update($task);
                }
            } catch (\Exception $exception) {
                // Handle errors
                $this->error('Task failed', [
                    'error_message' => $exception->getMessage(),
                    'error_code' => $exception->getCode(),
                ]);

                $task->setStatus(Task::STATUS_FAILED);
                $queue->update($task);

                continue;
            }
        }
    }

    function __destruct()
    {
        $this->debug('Worker stopped');
    }

    /**
     * Returns a unique name to help identifying this worker from logs.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getName();
    }
}
