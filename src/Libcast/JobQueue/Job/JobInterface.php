<?php

/*
 * This file is part of Libcast JobQueue component.
 *
 * (c) Brice Vercoustre <brcvrcstr@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE file 
 * that was distributed with this source code.
 */

namespace Libcast\JobQueue\Job;

interface JobInterface
{
    /**
     * May be used to set up a Job.
     * This method is called before perform().
     *
     * @return boolean
     */
    public function setup();

    /**
     * Must be used to perform the actual Job.
     *
     * @return boolean
     */
    public function perform();

    /**
     * May be used to perform some clean up.
     * This method is called after perform().
     *
     * @return boolean
     */
    public function terminate();

    /**
     *
     * @return mixed
     */
    public function execute();
}
