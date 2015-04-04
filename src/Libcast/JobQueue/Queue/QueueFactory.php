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

class QueueFactory 
{
    /**
     * Builds a Queue based on parameters.
     *
     * @param $parameters
     * @return RedisQueue
     * @throws QueueException
     */
    public static function build($parameters)
    {
        if (!is_array($parameters)) {
            $parameters = [
                'client' => $parameters,
            ];
        }

        if (!isset($parameters['client'])) {
            throw new QueueException('A valid client must be set in parameters.');
        }

        switch (true) {
            case $parameters['client'] instanceof \Predis\Client :
                return new RedisQueue($parameters['client']);
        }

        throw new QueueException('The submitted client is not yet supported.');
    }
}
