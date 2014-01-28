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

use Libcast\JobQueue\Queue\QueueInterface;
use Libcast\JobQueue\Task\TaskInterface;
use Psr\Log\LoggerInterface;

interface JobInterface
{
    /**
     * Get Job's class name
     * 
     * @return string
     */
    public function getClassName();

    /**
     * Check if $option exists
     * 
     * @param string $option
     * @return bool
     */
    public function hasOption($option);

    /**
     * 
     * @return array Options
     */
    public function getOptions();

    /**
     * Get a specific option
     * 
     * @param   string        $name   Option's name
     * @return  string|null           Option's value
     */
    public function getOption($name);

    /**
     * Check if $parameter exists
     * 
     * @param string $parameter
     * @return bool
     */
    public function hasParameter($parameter);

    /**
     * 
     * @return array Parameters
     */
    public function getParameters();

    /**
     * Get a specific parameter
     * 
     * @param   string        $name   Parameter's name
     * @return  string|null           Parameter's value
     */
    public function getParameter($name);

    /**
     * Setup the Job with its Task, the Queue that stores it and an optional
     * logger
     * 
     * @param \Libcast\JobQueue\Task\TaskInterface   $task
     * @param \Libcast\JobQueue\Queue\QueueInterface $queue
     * @param \Psr\Log\LoggerInterface          $logger
     */
    public function setup(TaskInterface $task, QueueInterface $queue, LoggerInterface $logger = null);

    /**
     * Executes preRun(), run() & postRun() methods of the Job
     * Requires to set a Task
     * 
     * @throws JobException
     */
    public function execute();  
}