<?php

namespace Libcast\Job\Job;

use Libcast\Job\Queue\QueueInterface;
use Libcast\Job\Task\TaskInterface;

use Psr\Log\LoggerInterface;

interface JobInterface
{
  /**
   * Get Job's name
   * 
   * @return string
   */
  public function getName();

  /**
   * Get Job's class name
   * 
   * @return string
   */
  public function getClassName();

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
   * @param \Libcast\Job\Task\TaskInterface   $task
   * @param \Libcast\Job\Queue\QueueInterface $queue
   * @param \Psr\Log\LoggerInterface          $logger
   */
  public function setup(TaskInterface $task, QueueInterface $queue, LoggerInterface $logger = null);

  /**
   * Start running the Job
   * Requires to set a Task
   * 
   * @throws JobException
   */
  public function execute();  
}