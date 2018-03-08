<?php

namespace JobQueue\Tests\Domain;

use JobQueue\Domain\Task\Profile;
use JobQueue\Domain\Task\Queue;
use JobQueue\Domain\Task\Status;
use JobQueue\Domain\Task\Task;
use JobQueue\Domain\Worker\Worker;
use JobQueue\Infrastructure\RedisQueue;
use JobQueue\Tests\Domain\Job;
use PHPUnit\Framework\TestCase;
use Predis\Client;

final class QueueTest extends TestCase
{
    /**
     *
     * @var Queue
     */
    private static $queue;

    /**
     *
     * @var Task
     */
    private static $testTask;

    /**
     *
     * @var Task
     */
    private static $erroneousTask;

    public static function setUpBeforeClass()
    {
        self::$testTask = new Task(
            new Profile('test'),
            new Job\DummyJob
        );

        self::$erroneousTask = new Task(
            new Profile('test'),
            new Job\ErroneousJob
        );

        self::$queue = new RedisQueue(new Client);
        self::$queue->add(self::$testTask);
        self::$queue->add(self::$erroneousTask);
    }

    /**
     *
     * @return Worker
     */
    public function testConsumeFIFOTask(): Worker
    {
        $worker = new Worker('test', self::$queue, new Profile('test'));
        $worker->consume(1);

        $task = self::$queue->find((string) self::$testTask->getIdentifier());

        $this->assertEquals(Status::FINISHED, (string) $task->getStatus());

        return $worker;
    }

    /**
     *
     * @depends testConsumeFIFOTask
     * @param Worker $worker
     */
    public function testConsumeErroneousTask(Worker $worker)
    {
        $worker->consume(1);

        $task = self::$queue->find((string) self::$erroneousTask->getIdentifier());

        $this->assertEquals(Status::FAILED, (string) $task->getStatus());
    }

    public static function tearDownAfterClass()
    {
        self::$queue->delete((string) self::$testTask->getIdentifier());
        self::$queue->delete((string) self::$erroneousTask->getIdentifier());
    }
}
