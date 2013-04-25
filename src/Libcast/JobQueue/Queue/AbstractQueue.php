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
   * @var array
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

  /**
   * 
   * @param array $parameters
   */
  protected function setParameters($parameters)
  {
    $this->parameters = $parameters;
  }

  /**
   * 
   * @param string      $name     Parameter name
   * @param string|null $default  Default value returned if no parameter
   */
  protected function getParameter($name, $default = null)
  {
    if (isset($this->parameters[$name]))
    {
      return $this->parameters[$name];
    }
    
    return $default;
  }

  /**
   * 
   * @param \Psr\Log\LoggerInterface
   */
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

  /**
   * Log message only if a logger has been set
   * 
   * @param   string  $message
   * @param   array   $contaxt
   * @param   string  $level    info|warning|error|debug
   */
  protected function log($message, $context = array(), $level = 'info')
  {
    if ($logger = $this->getLogger())
    {
      $logger->$level($message, $context);
    }
  }
  
  /**
   * List of options to sort Tasks by
   * 
   * @return array
   */
  public static function getSortByOptions()
  {
    return array(
        self::SORT_BY_PRIORITY,
        self::SORT_BY_PROFILE,
        self::SORT_BY_STATUS,
    );
  }

  /**
   * Connects the Queue to its database
   * 
   * @throws \Libcast\JobQueue\Exception\QueueException
   */
  protected function connect()
  {
    throw new QueueException('You must set a Queue database provider.');
  }
}