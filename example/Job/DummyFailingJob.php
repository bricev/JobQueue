<?php

namespace Libcast\JobQueue\TestJob;

use Libcast\JobQueue\Job\AbstractJob;
use Libcast\JobQueue\Job\JobInterface;
use Libcast\JobQueue\Exception\JobException;

class DummyFailingJob extends AbstractJob implements JobInterface
{
    public function perform()
    {
        throw new JobException('Dummy Job fails!');
    }
}
