<?php

namespace Libcast\JobQueue\Queue;

use Libcast\JobQueue\Exception\QueueException;

class QueueFactory 
{
  protected $queue = null;

  /**
   * Load Queue 
   * 
   * @param string                    $provider   Name of the database provider
   * @param array                     $parameters Provider parameters
   * @param \Psr\Log\LoggerInterface
   */
  function __construct($provider, array $parameters = array(), $logger = null)
  {
    $provider = ucfirst(strtolower($provider));
    $queue_class = sprintf('Libcast\\JobQueue\\Queue\\%sQueue', $provider);

    if (!class_exists($queue_class))
    {
      throw new QueueException("Class '{$provider}Queue' does not exists.");
    }

    $this->queue = new $queue_class($parameters, $logger);
  }
  
  /**
   * @return Libcast\JobQueue\Queue\QueueInterface 
   */
  public function getQueue()
  {
    return $this->queue;
  }
}