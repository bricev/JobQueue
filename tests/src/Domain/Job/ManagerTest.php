<?php

namespace JobQueue\Tests\Application\Console;

use JobQueue\Application\Manager\Manager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ManagerTest extends TestCase
{
    /**
     *
     * @var Manager
     */
    private static $manager;

    public static function setUpBeforeClass()
    {
        self::$manager = new Manager;
    }

    public function testAddCommand()
    {
        $commandTester = new CommandTester($command = self::$manager->get('add'));
        $commandTester->execute(['command' => $command->getName()], ['decorated' => false]);

var_dump($commandTester->getDisplay()); die;
    }
}
