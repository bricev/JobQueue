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

class NullJob extends AbstractJob implements JobInterface
{
    public function perform()
    {
        throw new JobException('This Job is not meant to be executed.');
    }
}
