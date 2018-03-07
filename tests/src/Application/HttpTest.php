<?php

namespace JobQueue\Tests\Application;

use GuzzleHttp\Client;
use JobQueue\Domain\Task\Profile;
use JobQueue\Domain\Task\Status;
use JobQueue\Domain\Task\Task;
use JobQueue\Tests\Domain\Job\DummyJob;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

final class HttpTest extends TestCase
{
    /**
     *
     * @var Process
     */
    public static $process;

    /**
     *
     * @var Client
     */
    public static $client;

    public static function setUpBeforeClass()
    {
        $command = sprintf('%1$s -S localhost:8085 -t %2$s/public %2$s/public/index.php',
            (new PhpExecutableFinder)->find(),
            dirname(dirname(dirname(realpath(__DIR__)))));

        self::$process = new Process($command);
        self::$process->start();

        self::$client = new Client([
            'base_uri' => 'http://localhost:8085',
        ]);
    }

    /**
     *
     * @return string
     */
    public function testAddTask(): string
    {
        $response = self::$client->post('/tasks', [
            'json' => new Task(
                new Profile('profile1'),
                new DummyJob,
                [
                    'param1' => 'value1',
                    'param2' => 'value2',
                ], [
                    'tag1', 'tag2'
                ]
            )
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $task = json_decode($response->getBody(), true);

        $uuidPattern = rtrim(ltrim(Uuid::VALID_PATTERN, '^'), '$');
        $this->assertEquals(1, preg_match("/$uuidPattern/", $task['identifier']), 'Missing identifier');

        $this->assertEquals(Status::WAITING, $task['status']);

        $this->assertEquals('profile1', $task['profile']);

        $this->assertTrue(isset($task['parameters']['param1']));
        $this->assertEquals('value1', $task['parameters']['param1']);


        $this->assertTrue(isset($task['tags']));
        $this->assertTrue(in_array('tag1', $task['tags']));

        return $task['identifier'];
    }

    /**
     *
     * @param string $identifier
     * @depends testAddTask
     */
    public function testShowTask(string $identifier)
    {
        $response = self::$client->get(sprintf('/task/%s', $identifier));

        $this->assertEquals(200, $response->getStatusCode());

        $task = json_decode($response->getBody(), true);

        $this->assertEquals(1, preg_match("/^$identifier$/", $task['identifier']), 'Missing identifier');

        $this->assertEquals(Status::WAITING, $task['status']);

        $this->assertEquals('profile1', $task['profile']);

        $this->assertTrue(isset($task['parameters']['param1']));
        $this->assertEquals('value1', $task['parameters']['param1']);


        $this->assertTrue(isset($task['tags']));
        $this->assertTrue(in_array('tag1', $task['tags']));
    }

    /**
     *
     * @depends testAddTask
     */
    public function testListTasks()
    {
        $task1Response = self::$client->post('/tasks', [
            'json' => new Task(
                new Profile('profile1'),
                new DummyJob,
                [
                    'param2' => 'value2',
                    'param31' => 'value3',
                ], [
                    'tag1', 'tag3'
                ]
            )
        ]);
        $this->assertEquals(200, $task1Response->getStatusCode());
        $task1 = json_decode($task1Response->getBody(), true);

        $task2Response = self::$client->post('/tasks', [
            'json' => new Task(
                new Profile('profile2'),
                new DummyJob,
                [
                    'param2' => 'value2',
                    'param31' => 'value3',
                ], [
                    'tag1', 'tag3'
                ]
            )
        ]);
        $this->assertEquals(200, $task2Response->getStatusCode());
        $task2 = json_decode($task2Response->getBody(), true);

        $tasksResponse = self::$client->get('/tasks');
        $this->assertEquals(200, $tasksResponse->getStatusCode());
        $tasks = json_decode($tasksResponse->getBody(), true);

        $this->assertEquals(3, count($tasks));

        $identifiers = [
            $task1['identifier'],
            $task2['identifier'],
        ];
        $this->assertTrue(in_array($tasks[1]['identifier'], $identifiers));
        $this->assertTrue(in_array($tasks[2]['identifier'], $identifiers));

        $profiles = [
            $task1['profile'],
            $task2['profile'],
        ];
        $this->assertTrue(in_array($tasks[1]['profile'], $profiles));
        $this->assertTrue(in_array($tasks[2]['profile'], $profiles));
    }

    public static function tearDownAfterClass()
    {
        self::$process->stop();
    }
}
