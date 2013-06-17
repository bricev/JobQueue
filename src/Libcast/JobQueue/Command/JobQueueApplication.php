<?php

namespace Libcast\JobQueue\Command;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;

use Libcast\JobQueue\Command\ListJobQueueCommand;
use Libcast\JobQueue\Command\EditJobQueueCommand;
use Libcast\JobQueue\Command\DeleteJobQueueCommand;
use Libcast\JobQueue\Command\QueueJobQueueCommand;

use Libcast\JobQueue\Queue\QueueInterface;

class JobQueueApplication extends Application
{
  protected $queue;

  protected $parameters;

  /**
   * Constructor.
   *
   * @param \Libcast\JobQueue\Queue\QueueInterface  $queue
   * @param array                                   $parameters
   *
   * @api
   */
  function __construct(QueueInterface $queue, array $parameters = array())
  {
    $this->setQueue($queue);
    $this->setParameters($parameters);

    return parent::__construct('Libcast Job Queue CLI', '0.3');
  }

  protected function getCommandName(InputInterface $input)
  {
    $command = $input->getFirstArgument();

    if (0 !== strpos($command, 'jobqueue:'))
    {
      $command = $command ? "jobqueue:$command" : null;
    }

    return $command;
  }

  protected function getDefaultCommands()
  {
    $defaultCommands = parent::getDefaultCommands();

    $defaultCommands[] = new ListJobQueueCommand;
    $defaultCommands[] = new EditJobQueueCommand;
    $defaultCommands[] = new DeleteJobQueueCommand;
    $defaultCommands[] = new FlushJobQueueCommand;
    $defaultCommands[] = new QueueJobQueueCommand;

    return $defaultCommands;
  }

  protected function setQueue(QueueInterface $queue)
  {
    $this->queue = $queue;
  }

  public function getQueue()
  {
    return $this->queue;
  }

  protected function setParameters(array $parameters)
  {
    $this->parameters = $parameters;
  }

  public function getParameters()
  {
    return $this->parameters;
  }

  public function getParameter($name)
  {
    return isset($this->parameters[$name]) ? $this->parameters[$name] : null;
  }
}