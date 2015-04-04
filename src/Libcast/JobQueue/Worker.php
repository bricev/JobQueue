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

use Libcast\JobQueue\Queue\QueueInterface;

class Worker
{
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
     * @var string
     */
    protected $profile;

    /**
     *
     * @var \Psr\Log\LoggerInterface|null
     */
    protected $logger;

    /**
     * Setup a Worker to connect the Queue.
     * The Worker will receive Tasks from Queue profiled sets.
     * Each Task will setup a Job that can be run (executed).
     *
     * @param string                                  $profile  Name of the `profile` handled by this worker
     * @param \Libcast\JobQueue\Queue\QueueInterface  $queue    Queue instance
     * @param \Psr\Log\LoggerInterface                $logger   Implementation of Psr\Log interface
     */
    public function __construct($profile, QueueInterface $queue, \Psr\Log\LoggerInterface $logger = null)
    {
        $this->setProfile($profile);
        $this->setQueue($queue);

        if ($logger) {
            $this->setLogger($logger);

            $this->log("Worker '$this' has started.");
        }

        $this->configurePHP();
    }

    /**
     * Set the profile handled by this Worker
     *
     * @param $profile
     */
    protected function setProfile($profile)
    {
        $this->profile = $profile;
    }

    /**
     *
     * @return string
     */
    protected function getProfile()
    {
        return $this->profile;
    }

    /**
     *
     * @param \Libcast\JobQueue\Queue\QueueInterface $queue
     */
    protected function setQueue(QueueInterface $queue)
    {
        $this->queue = $queue;
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
     * @param \Psr\Log\LoggerInterface $logger
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
     * Execute Tasks's jobs
     */
    public function run()
    {
        $queue = $this->getQueue(); /* @var $queue \Libcast\JobQueue\Queue\RedisQueue */

        while ($task = $queue->fetch($this->getProfile())) { /* @var $task \Libcast\JobQueue\Task */

            $this->log("Worker '$this' received Task '$task'", [
                'profile'    => $task->getProfile(),
                'job'        => (string) $task->getJob(),
                'parameters' => $task->getParameters(),
            ]);

            try {
                // get Job from Task
                $class = (string) $task->getJob();
                $job = new $class($task, $queue, $this->getLogger()); /* @var $job \Libcast\JobQueue\Job\JobInterface */

                // run Job
                if ($job->execute()) {
                    $this->log("Task '$task' has been successfully executed.");

                    // try to enqueue child Tasks,
                    $finished = true;
                    foreach ($task->getChildren() as $child) { /* @var $child \Libcast\JobQueue\Task */
                        $child->setParentId($task->getId());
                        $queue->shift($child);
                        $finished = false;
                    }

                    if ($finished) {
                        // no child: the Task is finished
                        $task->setStatus(Task::STATUS_FINISHED);
                        $queue->update($task);
                    } else {
                        // there is some work to be done with Task's children
                        // mark Task as success (not yet finished)
                        $task->setStatus(Task::STATUS_SUCCESS);
                        $queue->update($task);
                    }
                }
            } catch (\Exception $exception) {
                // Handle errors

                $this->log("Worker '$this' encountered an error with Task '$task'.", [
                    $exception->getMessage(),
                    $exception->getFile(),
                    $exception->getLine()
                ], 'error');

                $task->setStatus(Task::STATUS_FAILED);
                $queue->update($task);

                continue;
            }
        }
    }

    /**
     * Configure initial PHP settings to improve Worker's running
     *
     */
    protected function configurePHP()
    {
        // makes sure the Worker has space
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '-1');
        set_time_limit(0);

        $logger = $this->getLogger();

        // send errors to logger if exists
        set_error_handler(function ($code, $message, $file, $line, $context) use ($logger) {
            if ($logger) {
                switch ($code) {
                    case E_NOTICE:
                    case E_DEPRECATED:
                    case E_USER_NOTICE:
                    case E_USER_DEPRECATED:
                    case E_STRICT:
                    case E_WARNING:
                    case E_USER_WARNING:
                        $method = 'debug';
                        break;

                    default :
                        $method = 'error';
                }

                $logger->$method($message, [
                    'file'    => $file,
                    'line'    => $line,
                    'context' => $context,
                ]);
            }
        });
    }

    /**
     * Log message only if a logger has been set
     *
     * @param   string  $message
     * @param   array   $context
     * @param   string  $level    debug|info|notice|warning|error|critical|alert
     */
    protected function log($message, $context = array(), $level = 'info')
    {
        if ($logger = $this->getLogger()) {
            $logger->$level($message, $context);
        }
    }

    function __destruct()
    {
        $this->log("Worker '$this' stopped.");
    }

    /**
     * Returns a unique name to help identifying this worker from logs.
     *
     * @return string
     */
    public function __toString()
    {
        return sprintf('%s:%s (%s)',
                gethostname(),
                $this->getProfile(),
                uniqid());
    }
}
