<?php

namespace JobQueue\Tests\Domain\Job;

use JobQueue\Domain\Job\AbstractJob;
use JobQueue\Domain\Task\Task;

final class ErroneousJob extends AbstractJob
{
    /**
     *
     * @param Task $task
     */
    public function perform(Task $task)
    {
        $this->log('Dummy did a mistake! Very shameful! Much disgrace');
        throw new \Exception('Dummy mistake');
    }
}
