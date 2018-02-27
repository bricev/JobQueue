<?php

namespace JobQueue\Tests\Domain\Job;

use JobQueue\Domain\Job\AbstractJob;
use JobQueue\Domain\Task\Task;

final class DummyJob extends AbstractJob
{
    /**
     *
     * @param Task $task
     */
    public function perform(Task $task)
    {
        $this->log('Dummy done done! Very successful! Much ended');
    }
}
