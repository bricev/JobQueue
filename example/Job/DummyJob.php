<?php

namespace Libcast\JobQueue\TestJob;

use Libcast\JobQueue\Job\AbstractJob;
use Libcast\JobQueue\Job\JobInterface;

class DummyJob extends AbstractJob implements JobInterface
{
    public function perform()
    {
        // do dummy stuff...
        $duration = ceil($this->getParameter('duration', 3));
        for ($i=0; $i<$duration; $i++) {
            $this->setTaskProgress($i / $duration);
            sleep(1);
        }


        return true;
    }
}
