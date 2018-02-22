<?php

namespace JobQueue\Application\Utils;

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
        $this->logger->debug('Wow! Much start! Very dumb dumb');
        sleep(10); // I'm doing stuff, I swear!!
        $this->logger->debug('Dummy done done! Very successful! Much ended');
    }
}
