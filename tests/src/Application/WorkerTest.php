<?php

namespace JobQueue\Tests\Application;

use JobQueue\Application\Console\WorkerApplication;
use JobQueue\Domain\Task\Profile;
use JobQueue\Domain\Task\Queue;
use JobQueue\Domain\Task\Status;
use JobQueue\Domain\Task\Task;
use JobQueue\Infrastructure\ServiceContainer;
use JobQueue\Tests\Domain\Job;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class WorkerTest extends TestCase
{
    /**
     *
     * @var Queue
     */
    private static $queue;

    /**
     *
     * @var EventDispatcherInterface
     */
    private static $eventDispatcher;

    /**
     *
     * @var
     */
    private static $worker;

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
        self::$queue = ServiceContainer::getInstance()->queue;
        self::$eventDispatcher = ServiceContainer::getInstance()->dispatcher;
        self::$worker = new WorkerApplication(self::$queue, self::$eventDispatcher);

        self::$testTask = new Task(new Profile('test'), new Job\DummyJob);
        self::$erroneousTask = new Task(new Profile('test'), new Job\ErroneousJob);
        self::$queue->add(self::$testTask, self::$erroneousTask);
    }

    public function testConsume()
    {
        $commandTester = new CommandTester($command = self::$worker->get('consume'));
        $commandTester->execute([
            'profile' => 'test',
            '--name' => 'foobar',
            '--quantity' => 1,
        ], ['decorated' => false]);

        $this->assertContains('Worker foobar is done', $commandTester->getDisplay());

        $task = ServiceContainer::getInstance()
            ->queue
            ->find((string) self::$testTask->getIdentifier());

        $this->assertEquals((string) $task->getStatus(), Status::FINISHED);
    }

    public function testConsumeErroneousTask()
    {
        $commandTester = new CommandTester($command = self::$worker->get('consume'));
        $commandTester->execute([
            'profile' => 'test',
            '--name' => 'foobar',
            '--quantity' => 1,
        ], ['decorated' => false]);

        $this->assertContains('Worker foobar is done', $commandTester->getDisplay());

        $task = ServiceContainer::getInstance()
            ->queue
            ->find((string) self::$erroneousTask->getIdentifier());

        $this->assertEquals((string) $task->getStatus(), Status::FAILED);
    }

    public static function tearDownAfterClass()
    {
        ServiceContainer::getInstance()
            ->queue
            ->delete((string) self::$testTask->getIdentifier());

        ServiceContainer::getInstance()
            ->queue
            ->delete((string) self::$erroneousTask->getIdentifier());
    }
}
