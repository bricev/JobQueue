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

use Libcast\JobQueue\Exception\QueueException;
use Psr\Log\LoggerInterface;

class QueueFactory 
{
    /**
     * Load a Queue based on a DB client set from parameters.
     * 
     * @param   mixed                     $parameters Client or array of parameters
     * @param   \Psr\Log\LoggerInterface  $logger 
     * @param   \Swift_Mailer             $mailer     For Notification sending
     * @return  \Libcast\JobQueue\Queue\QueueInterface|false
     */
    public static function load($parameters, LoggerInterface $logger = null, \Swift_Mailer $mailer = null)
    {
        if (!is_array($parameters)) {
            $parameters = array(
                'client' => $parameters,
            );
        }

        if (!isset($parameters['client'])) {
            throw new QueueException('A valid DB client must be set in parameters.');
        }

        switch (true) {
            case $parameters['client'] instanceof \Predis\Client :
                return new RedisQueue($parameters['client'], $logger, $mailer);

            default :
                throw new QueueException('The submitted client is not yet supported.');
        }

        return false;
    }
}