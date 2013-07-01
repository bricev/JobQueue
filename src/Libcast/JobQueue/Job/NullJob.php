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

use Libcast\JobQueue\Exception\JobException;
use Libcast\JobQueue\Job\AbstractJob;
use Libcast\JobQueue\Job\JobInterface;

class NullJob extends AbstractJob implements JobInterface
{
    protected function initialize()
    {
        $this->setOptions(array(
            'priority'  => 0,
            'profile'   => 0,
        ));
    }

    protected function run()
    {
        throw new JobException('This Job is not meant to be executed.');
    }
}