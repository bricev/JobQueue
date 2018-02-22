<?php

namespace JobQueue\Domain\Job;

use JobQueue\Domain\Task\Task;
use Psr\Log\LoggerAwareTrait;

abstract class AbstractJob implements ExecutableJob
{
    use LoggerAwareTrait;

    /**
     *
     * @param Task $task
     */
    public function setUp(Task $task): void {}

    /**
     *
     * @param Task $task
     */
    abstract function perform(Task $task): void;

    /**
     *
     * @param Task $task
     */
    public function tearDown(Task $task): void {}
}
