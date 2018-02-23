<?php

namespace JobQueue\Tests;

use JobQueue\Domain\Job\AbstractJob;
use JobQueue\Domain\Task\Task;

final class DummyJob extends AbstractJob
{
    /**
     *
     * @param Task $task
     */
    function perform(Task $task): void
    {
        $this->log('Dummy done done! Very successful! Much ended');
    }
}
