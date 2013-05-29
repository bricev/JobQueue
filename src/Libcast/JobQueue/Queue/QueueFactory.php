<?php

namespace Libcast\JobQueue\Queue;

use Libcast\JobQueue\Exception\QueueException;

class QueueFactory 
{
  /**
   * Load a Queue based on a DB client set from parameters.
   * 
   * @param mixed $parameters Client or array of parameters
   * @param mixed $logger Logger object for debug
   * @return Libcast\JobQueue\Queue\QueueInterface|false
   */
  public static function load($parameters, $logger = null)
  {
    if (!is_array($parameters))
    {
      $parameters = array(
          'client' => $parameters,
      );
    }

    if (!isset($parameters['client']))
    {
      throw new QueueException('A valid DB client must be set in parameters.');
    }

    switch (true)
    {
      case $parameters['client'] instanceof \Predis\Client :
        return new RedisQueue($parameters['client'], $logger);
      
      default :
        throw new QueueException('The submitted client is not yet supported.');
    }

    return false;
  }
}