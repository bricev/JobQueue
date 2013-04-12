<?php

namespace Libcast\JobQueue\Queue;

use Libcast\JobQueue\Queue\QueueInterface;
use Libcast\JobQueue\Exception\QueueException;

use Psr\Log\LoggerInterface;

abstract class AbstractQueue implements QueueInterface
{
  const COMMON_PROFILE = 'common';
  
  const MAX_REQUEUE = 3;

  const PRIORITY_FAILED = -1;
  
  const PRIORITY_RUNNING = 0;
  
  const PRIORITY_MIN = 1;

  const SORT_BY_PRIORITY = 'priority';
  
  const SORT_BY_PROFILE = 'profile';
  
  const SORT_BY_STATUS = 'status';
  
  const ORDER_ASC = 'asc';
  
  const ORDER_DESC = 'desc';

  /**
   * @var $parameters Array containing provider paramaters
   */
  protected $parameters = array();

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;
          
  function __construct(array $parameters = array(), LoggerInterface $logger = null)
  {
    $this->setParameters($parameters);
    $this->connect();
    
    if ($logger)
    {
      $this->setLogger($logger);
    }
  }

  protected function setParameters($parameters)
  {
    $this->parameters = $parameters;
  }

  protected function getParameters($name, $default = null)
  {
    if (isset($this->parameters[$name]))
    {
      return $this->parameters[$name];
    }
    
    return $default;
  }

  public function setLogger(LoggerInterface $logger)
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

  public static function getSortByOptions()
  {
    return array(
        self::SORT_BY_PRIORITY,
        self::SORT_BY_PROFILE,
        self::SORT_BY_STATUS,
    );
  }

  protected function connect()
  {
    throw new QueueException('You must set a Queue database provider.');
  }
  
  protected function log($message, $context = array())
  {
    if ($logger = $this->getLogger())
    {
      return $logger->info($message, $context);
    }
  }
}