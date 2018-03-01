<?php

namespace JobQueue\Tests\Application;

use JobQueue\Application\Worker\Console;
use JobQueue\Domain\Task\Profile;
use JobQueue\Domain\Task\Status;
use JobQueue\Domain\Task\Task;
use JobQueue\Infrastructure\ServiceContainer;
use JobQueue\Tests\Domain\Job;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class WorkerTest extends TestCase
{
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
        self::$worker = new Console;

        self::$testTask = new Task(new Profile('test'), new Job\DummyJob);
        self::$erroneousTask = new Task(new Profile('test'), new Job\ErroneousJob);

        ServiceContainer::getInstance()
            ->queue
            ->add(self::$testTask, self::$erroneousTask);
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
}
