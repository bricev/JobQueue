<?php

namespace JobQueue\Tests\Domain\Job;

use JobQueue\Domain\Job\AbstractJob;
use JobQueue\Domain\Task\Task;

final class ComplexTOSnakeCASEJob extends AbstractJob
{
    /**
     *
     * @param Task $task
     */
    function perform(Task $task)
    {
        $this->log('This job has a name that is complex to convert to snake case');
    }
}
