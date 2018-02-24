<?php

namespace JobQueue\Tests\Utils;

use JobQueue\Domain\Job\AbstractJob;
use JobQueue\Domain\Task\Task;

final class ComplexTOSnakeCASE extends AbstractJob
{
    /**
     *
     * @param Task $task
     */
    function perform(Task $task): void
    {
        $this->log('This job has a name that is complex to convert to snake case');
    }
}
