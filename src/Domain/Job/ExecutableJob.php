<?php

namespace JobQueue\Domain\Job;

use JobQueue\Domain\Task\Task;
use Psr\Log\LoggerAwareInterface;

interface ExecutableJob extends LoggerAwareInterface
{
    /**
     *
     * @param Task $task
     */
    public function setUp(Task $task): void;

    /**
     *
     * @param Task $task
     */
    public function perform(Task $task): void;

    /**
     *
     * @param Task $task
     */
    public function tearDown(Task $task): void;
}
