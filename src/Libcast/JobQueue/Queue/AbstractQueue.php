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

abstract class AbstractQueue
{
    /**
     *
     * @var object
     */
    protected $client;

    /**
     *
     * @param $client
     */
    public function __construct($client)
    {
        $this->client = $client;
    }

    /**
     *
     * @return object
     */
    protected function getClient()
    {
        return $this->client;
    }
}
