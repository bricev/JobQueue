<?php

/*
 * This file is part of Libcast JobQueue component.
 *
 * (c) Brice Vercoustre <brcvrcstr@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE file 
 * that was distributed with this source code.
 */

namespace Libcast\JobQueue\Worker;

use Libcast\JobQueue\Exception\WorkerException;
use Libcast\JobQueue\Worker\WorkerInterface;
use Libcast\JobQueue\Queue\QueueInterface;
use Libcast\JobQueue\Task\Task;
use Psr\Log\LoggerInterface;

class Worker implements WorkerInterface
{
    const STATUS_BUSY = 'busy';

    const STATUS_PAUSED = 'paused';

    const MAX_REQUEUE = 3;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string busy|paused
     */
    protected $status = null;

    /**
     * @var \Libcast\JobQueue\Queue\QueueInterface
     */
    protected $queue;

    /**
     * @var array
     */
    protected $profiles;

    /**
     * @var array Failed Tasks count
     */
    protected $failed_count = array();

    /**
     * @var \Psr\Log\LoggerInterface|null
     */
    protected $logger;

    /**
     * Setup a Worker to connect the Queue.
     * The Worker will receive Tasks from Queue profiled sets.
     * Each Task will setup a Job that can be run (executed).
     * 
     * @param string                                  $name     Name of the Worker
     * @param \Libcast\JobQueue\Queue\QueueInterface  $queue    Queue instance
     * @param array                                   $profiles Profiles names (sets of Tasks)
     * @param \Psr\Log\LoggerInterface                $logger   Implementation of Psr\Log interface
     */
    function __construct($name, QueueInterface $queue, $profiles = array(), LoggerInterface $logger = null)
    {
        $this->setName($name);

        $this->setStatus(self::STATUS_PAUSED);

        $this->setQueue($queue);

        $this->setProfiles($profiles);

        if ($logger) {
            $this->setLogger($logger);
        }

        $this->configurePHP();
    }

    /**
     * Set a name used by logger to track this Worker activity
     * 
     * @param string $name
     */
    protected function setName($name)
    {
        $this->name = (string) $name;
    }

    /**
     * 
     * @return string
     */
    protected function getName()
    {
        return $this->name;
    }

    /**
     * 
     * @param string $status busy|paused
     */
    protected function setStatus($status)
    {
        if (!in_array($status, array(self::STATUS_BUSY, self::STATUS_PAUSED))) {
            throw new WorkerException("Status '$status' does not exists.");
        }

        $this->status = $status;
    }

    /**
     * 
     * @return string busy|paused
     */
    protected function getStatus()
    {
        return $this->status;
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
     * Set profiles handled by this Worker
     * 
     * @param array $profiles Array of profile names
     */
    protected function setProfiles($profiles)
    {
        $this->profiles = (array) $profiles;
    }

    /**
     * 
     * @return array
     */
    protected function getProfiles()
    {
        return $this->profiles;
    }

    /**
     * @param \Psr\Log\LoggerInterface $logger
     */
    protected function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return \Psr\Log\LoggerInterface 
     */
    protected function getLogger()
    {
        return $this->logger;
    }

    /**
     * {@inheritdoc}
     */
    public function run()
    {
        /* @hack avoid multiple worker startup to run a same Task twice */
        usleep(rand(0, 3000));

        $this->setStatus(self::STATUS_BUSY);

        try {
            $queue = $this->getQueue();

            $this->log(sprintf('Worker \'%s\' start taking Tasks from Queue with %s profile%s.', 
                    $this->getName(),
                    implode(', ', $this->getProfiles()),
                    count($this->getProfiles()) > 1 ? 's' : ''));

            while (true) {
                while ($task = $queue->getNextTask($this->getProfiles())) {
                    /* @var $task \Libcast\JobQueue\Task\TaskInterface */

                    $this->setStatus(self::STATUS_BUSY);

                    $this->log("Worker '$this' received Task '{$task->getId()}'", array(
                        'tag'       =>$task->getTag(),
                        'job'       => $task->getJob()->getClassName(),
                        'priority'  => $task->getOption('priority'),
                        'profile'   => $task->getOption('profile'),
                        'children'  => count($task->getChildren()),
                    ));

                    try {
                        // mark Task as running
                        $task->setStatus(Task::STATUS_RUNNING);
                        $queue->update($task);

                        // get Job from Task
                        $job = $task->getJob();
                        $job->setup($task, $queue, $this->getLogger());

                        // run Job
                        if ($job->execute()) {
                            $this->log("Task '$task' has been successfuly treated.");

                            // mark Task as success
                            $task->setStatus(Task::STATUS_SUCCESS);
                            $queue->update($task);

                            // try to enqueue child Tasks, 
                            $finished = true;
                            foreach ($task->getChildren() as $child) {
                                /* @var $child \Libcast\JobQueue\Task\Task */
                                $child->setParentId($task->getId());

                                $queue->add($child);

                                $finished = false;
                            }

                            if ($finished) {
                                // mark Task as finished
                                $task->setStatus(Task::STATUS_FINISHED);
                                $queue->update($task);
                            }
                        } else {
                            // this should not happen...
                            throw new WorkerException('Unknown error.');
                        }
                    } catch (\Exception $exception) {
                        $this->log("Worker '$this' encountered an error with Task '$task'.", array(
                            $exception->getMessage(),
                            $exception->getFile(),
                            $exception->getLine()
                        ), 'error');

                        try {
                            $queue->incrFailed($task);

                            $fail_count = $queue->countFailed($task);

                            if (self::MAX_REQUEUE > $fail_count) {
                                // give the Task another chance...
                                $this->log(sprintf('Failed Task \'%d\' has been given another chance (%d/%d)',
                                        $task->getId(),
                                        $fail_count,
                                        self::MAX_REQUEUE));

                                // schedule Task to let next in Queue being treated before
                                $queue->schedule($task, time() + 30);
                            } else {
                                // ... or mark it as failed if too many attempts
                                $this->log("Task '{$task->getId()}' failed $fail_count times : Worker gave up.");

                                // mark Task as failed
                                $task->setStatus(Task::STATUS_FAILED);
                                $queue->update($task);
                            }
                        } catch (\Exception $exception) {
                            $this->log("Worker '$this' can't mark Task '$task' as failed.", array(
                                $exception->getMessage(),
                                $exception->getFile(),
                                $exception->getLine()
                            ), 'error');

                            continue;
                        }

                        continue;
                    }

                    sleep(3); // give CPU some rest
                }

                // log pause
                if (self::STATUS_BUSY === $this->getStatus()) {
                    $this->setStatus(self::STATUS_PAUSED);
                    $this->log("Worker '$this' has been paused.");
                }

                sleep(10); // no more Task, let's sleep a little bit longer
            }
        } catch (\Exception $exception) {
            $this->log("Worker '$this' has encountered an error.", array(
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
                $exception->getTraceAsString(),
            ), 'error');
        }
    }

    /**
     * Configure initial PHP settings to improve Worker's running
     */
    protected function configurePHP()
    {
        // makes sure the Worker has space
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '-1');
        set_time_limit(0);

        $logger = $this->getLogger();

        // send errors to logger if exists
        set_error_handler(
            function (
                $code, 
                $message, 
                $file, 
                $line, 
                $context
            ) use ($logger) {
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

                    $logger->$method($message, array(
                        'file'    => $file,
                        'line'    => $line,
                        'context' => $context,
                    ));
                }
            }
        );
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
        $this->log("Worker '$this' stoped.");
    }

    public function __toString()
    {
        return $this->getName();
    }
}