<?php

namespace JobQueue\Tests\Utils;

use JobQueue\Domain\Job\AbstractJob;
use JobQueue\Domain\Task\Task;

final class ErroneousJob extends AbstractJob
{
    /**
     *
     * @param Task $task
     */
    function perform(Task $task): void
    {
        $this->log('Dummy did a mistake! Very shameful! Much disgrace');
        throw new \Exception('Dummy mistake');
    }
}
