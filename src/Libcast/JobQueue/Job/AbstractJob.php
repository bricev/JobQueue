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
use Libcast\JobQueue\Job\JobInterface;
use Libcast\JobQueue\Queue\QueueInterface;
use Libcast\JobQueue\Queue\AbstractQueue;
use Libcast\JobQueue\Task\TaskInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractJob implements JobInterface
{
    /**
     * @var \Libcast\JobQueue\Queue\QueueInterface
     */
    protected $queue = null;

    /**
     * @var \Libcast\JobQueue\Task\Task
     */
    protected $task = null;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger = null;

    /**
     * @var array
     */
    protected $options = array();

    /**
     * @var array
     */
    protected $required_options = array(
        'priority',
        'profile',
    );

    /**
     * @var array
     */
    protected $parameters = array();

    /**
     *
     * @var bool
     */
    protected $force_stop = false;

    /**
     * @var array
     */
    protected $required_parameters = array();

    public function __construct()
    {
        $this->initialize();
    }

    /**
     * {@inheritdoc}
     */
    public function getClassName()
    {
        return get_class($this);
    }

    /**
     *
     * @param array   $options  Array of required option names
     */
    protected function setRequiredOptions($options)
    {
        $this->required_options = array_merge($this->required_options, (array) $options);
    }

    /**
     *
     * @param array   $options  Array of options to set up
     */
    protected function setOptions($options)
    {
        // check if all required options have been registred
        // will throw an exception if any option is messing
        $this->validateArguments($options, $this->required_options, 'option');

        $this->options = array_merge($this->options, (array) $options);
    }

    /**
     * {@inheritdoc}
     */
    public function hasOption($option)
    {
        return in_array($option, array_keys($this->getOptions()));
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * {@inheritdoc}
     */
    public function getOption($name)
    {
        if (!isset($this->options[$name])) {
            throw new JobException("The option '$name' does not exists.");
        }

        return $this->options[$name];
    }

    protected function setRequiredParameters($parameters)
    {
        $this->required_parameters = array_merge($this->required_parameters, (array) $parameters);
    }

    protected function setParameters($parameters)
    {
        $parameters = array_merge($this->parameters, (array) $parameters);

        // check if all required parameters have been registred
        // will throw an exception if any parameter is messing
        $this->validateArguments($parameters, $this->required_parameters, 'parameter');

        $this->parameters = $parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function hasParameter($parameter)
    {
        return in_array($parameter, array_keys($this->getParameters()));
    }

    /**
     * {@inheritdoc}
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function getParameter($name)
    {
        if (!isset($this->parameters[$name])) {
            throw new JobException("The parameter '$name' does not exists.");
        }

        return $this->parameters[$name];
    }

    /**
     * Sets a PSR valid logger
     *
     * @param   \Psr\Log\LoggerInterface  $logger
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
     * Log message only if a logger has been set
     *
     * @param   string  $message
     * @param   array   $contaxt
     * @param   string  $level    info|warning|error|debug
     */
    protected function log($message, $context = array(), $level = 'info')
    {
        if ($logger = $this->getLogger()) {
            $logger->$level($message, $context);
        }
    }

    /**
     * Log an error
     *
     * @param   string  $message
     * @param   array   $contaxt
     */
    protected function error($message, $context = array())
    {
        $this->log($message, $context, 'error');
    }

    /**
     * Makes sure all $required_args are listed in $args list.
     *
     * @param array   $args           Array of arguments
     * @param array   $required_args  List of mandatory arguments
     * @param string  $arg_type       argument|option|parameter
     */
    protected function validateArguments($args, $required_args, $arg_type = 'argument')
    {
        foreach ($required_args as $arg) {
            if (!in_array($arg, array_keys($args))) {
                throw new JobException("The $arg_type '$arg' is missing.");
            }

            if (!strstr($this->getClassName(), 'NullJob')
                    && 'option' === $arg_type
                    && 'priority' === $arg
                    && (!is_int($args[$arg]) || $args[$arg] < AbstractQueue::PRIORITY_MIN)) {
                throw new JobException(sprintf(
                                'Task priority must be bigger or equal to \'%d\'. Value \'%d\' given.',
                                AbstractQueue::PRIORITY_MIN,
                                $args[$arg]));
            }
        }

        return true;
    }

    /**
     * Update Task's progress and persist data
     *
     * @param   float $percent
     * @throws  \Libcast\JobQueue\Exception\JobException
     */
    protected function setTaskProgress($percent)
    {
        if (!$queue = $this->queue) {
            throw new JobException('There is no Queue to update Task progress.');
        }

        if (!$task = $this->task) {
            throw new JobException('There is no Task to set progress to.');
        }

        $task->setProgress((float) $percent);
        $queue->update($task);
    }

    /**
     * {@inheritdoc}
     */
    public function setup(TaskInterface $task, QueueInterface $queue, LoggerInterface $logger = null)
    {
        $this->setOptions($task->getOptions());

        $this->setParameters($task->getParameters());

        $this->task = $task;

        $this->queue = $queue;

        if ($logger) {
            $this->setLogger($logger);
        }
    }

    /**
     *
     * @return bool
     */
    public function isStopped()
    {
        return $this->force_stop;
    }

    /**
     *
     * @return bool true
     */
    public function forceStop()
    {
        return $this->force_stop = true;
    }

    /**
     * Executed before Task work
     * Exemple of use:
     * - test that a file exists
     * - launch a specific application
     */
    protected function preRun()
    {
        return true;
    }

    /**
     * Do the actual Job
     */
    protected function run()
    {
        return true;
    }

    /**
     * Executed after Task work
     * Example of use:
     * - clean temp files
     * - create more tasks
     * - cancel a specific application
     */
    protected function postRun()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        if (!$queue = $this->queue) {
            throw new JobException('A Queue is required to run a Job.');
        }

        if (!$task = $this->task) {
            throw new JobException('A Task is required to run a Job.');
        }

        switch (true) {
            case !$this->preRun() || $this->isStopped():
                $type = 'pre';
                // no break

            case !$this->run() || $this->isStopped():
                $type = isset($type) ? $type : 'main';
                // no break

            case !$this->postRun() || $this->isStopped():
                $type = isset($type) ? $type : 'post';

                if ($this->isStopped()) {
                    break; // if Job has been stopped manually, dont throw exception
                }

                throw new JobException("Running the $type Job failed.");
        }

        return true;
    }

    public function __toString()
    {
        return (string) $this->getClassName();
    }
}
