<?php

namespace JobQueue\Tests\Application\Console;

use JobQueue\Application\Manager\Console;
use JobQueue\Domain\Task\Status;
use JobQueue\Infrastructure\ServiceContainer;
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
            'job' => 'JobQueue\Tests\Domain\Job\DummyJob'
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

        $this->assertEquals(1, preg_match("/Identifier +: $identifier/", $commandTester->getDisplay(), $matches));
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

        $this->assertEquals(1, preg_match("/$identifier/", $commandTester->getDisplay(), $matches));
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

        $this->assertEquals(1, preg_match("/Identifier +: $identifier/", $commandTester->getDisplay(), $matches));
        $this->assertEquals(1, preg_match("/Status +: $status/", $commandTester->getDisplay(), $matches));
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
            ->dump();

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
            ->dump();

        $this->assertTrue(empty($tasks));
    }
}
