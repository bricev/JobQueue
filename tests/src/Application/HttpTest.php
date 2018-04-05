<?php

namespace JobQueue\Tests\Application;

use JobQueue\Application\Http\AddTask;
use JobQueue\Application\Http\ListTasks;
use JobQueue\Application\Http\ShowTask;
use JobQueue\Domain\Task\Profile;
use JobQueue\Domain\Task\Status;
use JobQueue\Domain\Task\Task;
use JobQueue\Infrastructure\ServiceContainer;
use JobQueue\Tests\Domain\Job\DummyJob;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Zend\Diactoros\ServerRequest;

final class HttpTest extends TestCase
{
    /**
     *
     * @return string
     */
    public function testAddTask(): string
    {
        $queue = ServiceContainer::getInstance()->queue;

        $task = new Task(
            new Profile('profile1'),
            new DummyJob,
            [
                'param1' => 'value1',
                'param2' => 'value2',
            ], [
                'tag1', 'tag2'
            ]
        );

        $request = new ServerRequest([], [], '/tasks', 'POST', 'php://input', [
            'content-type' => 'application/json',
        ], [], [], $task->jsonSerialize());

        $response = (new AddTask($queue))->handle($request);
        $this->assertEquals(201, $response->getStatusCode());

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
        $queue = ServiceContainer::getInstance()->queue;

        $request = new ServerRequest([], [], sprintf('/task/%s', $identifier), 'GET');
        $request = $request->withAttribute('identifier', $identifier);

        $response = (new ShowTask($queue))->handle($request);
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
        $queue = ServiceContainer::getInstance()->queue;

        $task1 = new Task(
            new Profile('profile1'),
            new DummyJob,
            [
                'param1' => 'value1',
                'param2' => 'value2',
            ], [
                'tag1', 'tag2'
            ]
        );

        $request1 = new ServerRequest([], [], '/tasks', 'POST', 'php://input', [
            'content-type' => 'application/json',
        ], [], [], $task1->jsonSerialize());

        $response1 = (new AddTask($queue))->handle($request1);
        $this->assertEquals(201, $response1->getStatusCode());

        $task2 = new Task(
            new Profile('profile1'),
            new DummyJob,
            [
                'param1' => 'value1',
                'param2' => 'value2',
            ], [
                'tag1', 'tag2'
            ]
        );

        $request2 = new ServerRequest([], [], '/tasks', 'POST', 'php://input', [
            'content-type' => 'application/json',
        ], [], [], $task2->jsonSerialize());

        $response2 = (new AddTask($queue))->handle($request2);
        $this->assertEquals(201, $response2->getStatusCode());

        $request = new ServerRequest([], [], '/tasks', 'GET');

        $response = (new ListTasks($queue))->handle($request);
        $this->assertEquals(200, $response->getStatusCode());

        $tasks = json_decode($response->getBody(), true);

        $this->assertEquals(3, count($tasks));

        $profiles = [
            'profile1',
            'profile2',
        ];
        $this->assertTrue(in_array($tasks[1]['profile'], $profiles));
        $this->assertTrue(in_array($tasks[2]['profile'], $profiles));
    }
}
