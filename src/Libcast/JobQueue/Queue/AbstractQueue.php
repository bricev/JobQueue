<?php

/*
 * This file is part of Libcast JobQueue component.
 *
 * (c) Brice Vercoustre <brcvrcstr@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

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
     * @var \Swift_Mailer
     */
    protected $mailer;

    /**
     *
     * @param   object                    $client DB client (eg. \Predis\Client)
     * @param   \Psr\Log\LoggerInterface  $logger
     * @param   \Swift_Mailer             $mailer For Notification sending
     * @throws  \Libcast\JobQueue\Exception\QueueException
     */
    public function __construct($client, LoggerInterface $logger = null, \Swift_Mailer $mailer = null)
    {
        if (!$client) {
            throw new QueueException('Please provide a valid client.');
        }

        $this->client = $client;

        if ($logger) {
            $this->setLogger($logger);
        }

        if ($mailer) {
            $this->setMailer($mailer);
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
     *
     * @return \Psr\Log\LoggerInterface
     */
    protected function getLogger()
    {
        return $this->logger;
    }

    /**
     *
     * @param \Swift_Mailer $mailer
     */
    protected function setMailer(\Swift_Mailer $mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     *
     * @return \Swift_Mailer
     */
    protected function getMailer()
    {
        return $this->mailer;
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
        if ($logger = $this->getLogger()) {
            $logger->$level($message, $context);
        }
    }
}