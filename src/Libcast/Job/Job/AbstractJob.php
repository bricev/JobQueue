<?php

namespace Libcast\Job\Job;

use Libcast\Job\Exception\JobException;
use Libcast\Job\Job\JobInterface;
use Libcast\Job\Queue\QueueInterface;
use Libcast\Job\Queue\AbstractQueue;
use Libcast\Job\Task\TaskInterface;

use Psr\Log\LoggerInterface;

abstract class AbstractJob implements JobInterface
{
  /**
   * @var string Name of the Job
   */
  protected $name;

  /**
   * @var \Libcast\Job\Queue\QueueFactory
   */
  protected $queue = null;
  
  /**
   * @var \Libcast\Job\Task\Task
   */
  protected $task = null;
  
  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger = null;

  /**
   * @var arrays
   */
  protected $options = array(),
            $required_options = array(
                'priority',
                'profile',
            );
  
  /**
   * @var arrays
   */
  protected $parameters = array(),
            $required_parameters = array();
  
  function __construct()
  {
    $this->initialize();
  }

  protected function setName($name)
  {
    $this->name = (string) $name;
  }

  public function getName()
  {
    return $this->name;
  }

  public function getClassName()
  {
    return get_class($this);
  }

  protected function setRequiredOptions($options)
  {
    $this->required_options = array_merge($this->required_options, (array) $options);
  }
  
  protected function setOptions($options)
  {
    // check if all required options have been registred
    // will throw an exception if any option is messing
    $this->validateArguments($options, $this->required_options, 'option');

    $this->options = array_merge($this->options, (array) $options);
  }

  public function getOptions()
  {
    return $this->options;
  }

  public function getOption($name)
  {
    if (!isset($this->options[$name]))
    {
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
    // check if all required parameters have been registred
    // will throw an exception if any parameter is messing
    $this->validateArguments($parameters, $this->required_parameters, 'parameter');

    $this->parameters = array_merge($this->parameters, (array) $parameters);
  }
  
  public function getParameters()
  {
    return $this->parameters;
  }

  public function getParameter($name)
  {
    if (!isset($this->parameters[$name]))
    {
      throw new JobException("The parameter '$name' does not exists.");
    }
    
    return $this->parameters[$name];
  }
  
  /**
   * Makes sure all $required_args are listed in $args list.
   */
  protected function validateArguments($args, $required_args, $arg_type = 'argument')
  {
    foreach ($required_args as $arg)
    {
      if (!in_array($arg, array_keys($args)))
      {
        throw new JobException("The $arg_type '$arg' is missing.");
      }
      
      if (!strstr($this->getClassName(), 'NullJob') && 
              'option' === $arg_type && 
              'priority' === $arg &&
              (!is_int($args[$arg]) || $args[$arg] < AbstractQueue::PRIORITY_MIN))
      {
        throw new JobException(sprintf('Task priority must be bigger or equal to \'%d\'. Value \'%d\' given.',
                AbstractQueue::PRIORITY_MIN,
                $args[$arg]));
      }
    }
    
    return true;
  }
  
  protected function setTaskProgress($percent)
  {
    if (!$this->task)
    {
      throw new JobException('There is no Task to set progress to.');
    }

    if (!$this->queue)
    {
      throw new JobException('There is no Queue to update Task progress.');
    }

    $this->task->setProgress($percent);
    $this->queue->update($this->task);
  }

  public function setup(TaskInterface $task, QueueInterface $queue, LoggerInterface $logger = null)
  {
    $this->setOptions($task->getOptions());
    
    $this->setParameters($task->getParameters());
    
    $this->task = $task;
    
    $this->queue = $queue;
    
    $this->logger = $logger;
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

  public function execute() 
  {
    if (!$queue = $this->queue)
    {
      throw new JobException('A Queue is required to run a Job.');
    }
    
    if (!$task = $this->task)
    {
      throw new JobException('A Task is required to run a Job.');
    }

    try 
    {
      if (!$this->preRun())
      {
        throw new JobException('Running the pre Job failed.');
      }
      
      if (!$this->run())
      {
        throw new JobException('Running the main Job failed.');
      }
      
      if (!$this->postRun())
      {
        throw new JobException('Running the post Job failed.');
      }
    }
    catch (\Exception $exception)
    {
      if ($this->logger)
      {
        $this->logger->error("Job execution failed with error '{$exception->getMessage()}'.");
      }
      
      return false;
    }
    
    return true;
  }
  
  public function __toString()
  {
    return (string) $this->getClassName();
  }
}