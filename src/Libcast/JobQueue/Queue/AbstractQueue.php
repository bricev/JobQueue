<?php

namespace Libcast\JobQueue\Queue;

use Libcast\JobQueue\Queue\QueueInterface;
use Libcast\JobQueue\Exception\QueueException;

use Psr\Log\LoggerInterface;

abstract class AbstractQueue implements QueueInterface
{
  const COMMON_PROFILE = 'common';

  const PRIORITY_MIN = 1;

  const SORT_BY_PRIORITY = 'priority';

  const SORT_BY_PROFILE = 'profile';

  const SORT_BY_STATUS = 'status';

  const ORDER_ASC = 'asc';

  const ORDER_DESC = 'desc';

  /**
   * @var object
   */
  protected $client;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * 
   * @param object                    $client DB client (eg. \Predis\Client)
   * @param \Psr\Log\LoggerInterface  $mailer
   */
  function __construct($client, LoggerInterface $logger = null)
  {
    if (!$client)
    {
      throw new QueueException('Please provide a valid client.');
    }

    $this->client = $client;

    if ($logger)
    {
      $this->setLogger($logger);
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
}