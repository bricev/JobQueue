<?php

namespace JobQueue\Tests\Application;

use JobQueue\Application\Manager\Console;
use JobQueue\Domain\Task\Profile;
use JobQueue\Domain\Task\Status;
use JobQueue\Domain\Task\Task;
use JobQueue\Infrastructure\ServiceContainer;
use JobQueue\Tests\Domain\Job\DummyJob;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Tester\CommandTester;

final class ManagerTest extends TestCase
{
    /**

     * @var Console
     */
    private static $manager;

    public static function setUpBeforeClass()
    {
        self::$manager = new Console;
    }

    /**
     *
     * @return string
     */
    public function testAddCommand(): string
    {
        $commandTester = new CommandTester($command = self::$manager->get('add'));
        $commandTester->execute([
            'command' => $command->getName(),
            'profile' => 'foo',
            'job' => 'JobQueue\Tests\Domain\Job\DummyJob',
            'parameters' => [
                'param1:value1',
                'param2:value2',
            ],
            '--tags' => ['tag1', 'tag2'],
        ], ['decorated' => false]);

        $uuidPattern = rtrim(ltrim(Uuid::VALID_PATTERN, '^'), '$');

        $this->assertEquals(1, preg_match("/Identifier +: ($uuidPattern)/", $commandTester->getDisplay(), $matches));

        return $matches[1];
    }

    /**
     *
     * @depends testAddCommand
     * @param string $identifier
     */
    public function testShowCommand(string $identifier)
    {
        $commandTester = new CommandTester($command = self::$manager->get('show'));
        $commandTester->execute([
            'command' => $command->getName(),
            'identifier' => $identifier,
        ], ['decorated' => false]);

        $this->assertEquals(1, preg_match("/Identifier +: $identifier/", $commandTester->getDisplay()), 'Missing identifier');
        $this->assertEquals(1, preg_match('/Parameters +: 1\) param1: value1/', $commandTester->getDisplay()), 'Missing parameter');
        $this->assertEquals(1, preg_match('/Tags +: tag1/', $commandTester->getDisplay()), 'Missing tag');
    }

    /**
     *
     * @depends testAddCommand
     * @param string $identifier
     */
    public function testListCommand(string $identifier)
    {
        $commandTester = new CommandTester($command = self::$manager->get('list'));
        $commandTester->execute([
            'command' => $command->getName(),
        ], ['decorated' => false]);

        $this->assertEquals(1, preg_match("/$identifier/", $commandTester->getDisplay()), 'Missing identifier');
    }

    /**
     *
     * @depends testAddCommand
     * @param string $identifier
     */
    public function testListCommandWithTagFilter(string $identifier): string
    {
        $queue = ServiceContainer::getInstance()->queue;
        $queue->add(new Task(
            new Profile('test'),
            new DummyJob,
            [], ['foo', 'bar']
        ), $task2 = new Task(
            new Profile('test2'),
            new DummyJob,
            [], ['bar', 'baz']
        ));

        $commandTester = new CommandTester($command = self::$manager->get('list'));
        $commandTester->execute([
            'command' => $command->getName(),
            '--tags' => ['tag1'],
            '--legend' => true,
        ], ['decorated' => false]);

        $this->assertEquals(1, preg_match("/$identifier/", $commandTester->getDisplay()), 'Missing identifier');
        $this->assertEquals(1, preg_match('/T1: tag "tag1"/', $commandTester->getDisplay()), 'Missing tag legend');

        return (string) $task2->getIdentifier();
    }

    /**
     *
     * @depends testListCommandWithTagFilter
     * @param string $identifier
     */
    public function testListCommandWithProfileFilter(string $identifier)
    {
        $commandTester = new CommandTester($command = self::$manager->get('list'));
        $commandTester->execute([
            'command' => $command->getName(),
            '--profile' => 'test2',
            '--legend' => true,
        ], ['decorated' => false]);

        $this->assertEquals(1, preg_match("/$identifier/", $commandTester->getDisplay()), 'Missing identifier');
    }

    /**
     *
     * @depends testAddCommand
     * @param string $identifier
     */
    public function testEditCommand(string $identifier)
    {
        $commandTester = new CommandTester($command = self::$manager->get('edit'));
        $commandTester->execute([
            'command' => $command->getName(),
            'identifier' => $identifier,
            'status' => $status = Status::FINISHED,
        ], ['decorated' => false]);

        $this->assertEquals(1, preg_match("/Identifier +: $identifier/", $commandTester->getDisplay()), 'Missing identifier');
        $this->assertEquals(1, preg_match("/Status +: $status/", $commandTester->getDisplay()), 'Missing status');
    }

    /**
     *
     * @depends testEditCommand
     */
    public function testRestoreCommand()
    {
        $commandTester = new CommandTester($command = self::$manager->get('restore'));
        $commandTester->execute([
            'command' => $command->getName(),
            '--force' => true,
        ], ['decorated' => false]);

        $tasks = ServiceContainer::getInstance()
            ->queue
            ->search();

        foreach ($tasks as $task) {
            $this->assertEquals(Status::WAITING, (string) $task->getStatus());
        }
    }

    /**
     *
     * @depends testRestoreCommand
     */
    public function testFlushCommand()
    {
        $commandTester = new CommandTester($command = self::$manager->get('flush'));
        $commandTester->execute([
            'command' => $command->getName(),
            '--force' => true,
        ], ['decorated' => false]);

        $tasks = ServiceContainer::getInstance()
            ->queue
            ->search();

        $this->assertTrue(empty($tasks));
    }
}
