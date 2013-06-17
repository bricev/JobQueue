<?php

namespace Libcast\JobQueue\Command;

use Symfony\Component\Console\Command\Command;

use Libcast\JobQueue\Exception\CommandException;
use Libcast\JobQueue\Command\JobQueueApplication;

class JobQueueCommand extends Command
{  
  protected $lines = array('');

  /**
   * 
   * @return \Libcast\JobQueue\Queue\QueueInterface
   */
  protected function getQueue()
  {   
    return $this->getApplication()->getQueue();
  }
  
  /**
   * Gets the application instance for this command.
   *
   * @return \Libcast\JobQueue\Command\JobQueueApplication
   */
  public function getApplication()
  {
    $application = parent::getApplication();

    if (!$application instanceof JobQueueApplication)
    {
      throw new CommandException('This application is not valid.');
    }

    return $application;
  }

  protected function addLine($line = null)
  {
    if (!$line)
    {
      $line = '';
    }
    
    if (is_array($line))
    {
      $this->lines = array_merge($this->lines, $line);
      return;
    }
    
    $this->lines[] = $line;
  }
  
  protected function getLines()
  {
    $this->lines[] = '';

    return $this->lines;
  }
  
  protected function flushLines()
  {
    $this->lines = array('');
  }
}