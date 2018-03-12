<?php

namespace JobQueue\Tests\Infrastructure;

use JobQueue\Domain\Task\Profile;
use JobQueue\Domain\Task\Queue;
use JobQueue\Domain\Task\Status;
use JobQueue\Domain\Task\Task;
use JobQueue\Infrastructure\RedisQueue;
use JobQueue\Tests\Domain\Job\DummyJob;
use PHPUnit\Framework\TestCase;
use Predis\Client;

final class RedisQueueTest extends TestCase
{
    /**
     *
     * @var Task[]
     */
    private static $tasks = [];

    public static function setUpBeforeClass()
    {
        self::$tasks[] = new Task(
            new Profile('profile1'),
            new DummyJob,
            [
                'param1' => 'value1a',
                'param2' => 'value2a',
            ],
            [
                'tag1',
                'tag2',
            ]
        );
        self::$tasks[] = new Task(
            new Profile('profile1'),
            new DummyJob,
            [
                'param1' => 'value1b',
                'param3' => 'value3b',
            ],
            [
                'tag1',
                'tag3',
            ]
        );
        self::$tasks[] = new Task(
            new Profile('profile2'),
            new DummyJob,
            [
                'param2' => 'value2c',
                'param3' => 'value3c',
            ],
            [
                'tag2',
                'tag4',
            ]
        );
    }

    /**
     *
     * @return RedisQueue
     */
    public function testCreateQueue(): RedisQueue
    {
        $queue = new RedisQueue(new Client);

        $this->assertInstanceOf(Queue::class, $queue);

        return $queue;
    }

    /**
     *
     * @param RedisQueue $queue
     * @depends testCreateQueue
     */
    public function testAddTasks(RedisQueue $queue)
    {
        foreach (self::$tasks as $task) {
            $queue->add($task);
        }

        $this->assertEquals(3, count($queue->search()));
    }

    /**
     *
     * @param RedisQueue $queue
     * @depends testCreateQueue
     */
    public function testFindTask(RedisQueue $queue)
    {
        $identifier = (string) self::$tasks[0]->getIdentifier();

        $task = $queue->find($identifier);

        $this->assertEquals($identifier, (string) $task->getIdentifier());
    }

    /**
     *
     * @param RedisQueue $queue
     * @return RedisQueue
     * @depends testCreateQueue
     */
    public function testFetchTask(RedisQueue $queue): RedisQueue
    {
        $task = $queue->fetch(new Profile('profile1'));

        $this->assertEquals(Status::RUNNING, (string) $task->getStatus());
        $this->assertEquals((string) self::$tasks[0]->getIdentifier(), (string) $task->getIdentifier());

        return $queue;
    }

    /**
     *
     * @param RedisQueue $queue
     * @depends testFetchTask
     */
    public function testSearchTasks(RedisQueue $queue)
    {
        $this->assertEquals(2, count($queue->search(new Profile('profile1'))));
        $this->assertEquals(1, count($queue->search(new Profile('profile2'))));
        $this->assertEquals(2, count($queue->search(null, new Status(Status::WAITING))));
        $this->assertEquals(1, count($queue->search(null, new Status(Status::RUNNING))));
        $this->assertEquals(2, count($queue->search(null, null, ['tag1'])));
        $this->assertEquals(1, count($queue->search(null, null, ['tag4'])));
        $this->assertEquals(3, count($queue->search(null, null, ['tag1', 'tag4'])));
    }

    /**
     *
     * @param RedisQueue $queue
     * @return RedisQueue
     * @depends testFetchTask
     */
    public function testUpdateTask(RedisQueue $queue): RedisQueue
    {
        $identifier = (string) self::$tasks[1]->getIdentifier();

        $task = $queue->fetch(new Profile('profile1'));
        $this->assertEquals(Status::RUNNING, (string) $task->getStatus());
        $this->assertEquals($identifier, (string) $task->getIdentifier());

        $queue->updateStatus($task, new Status(Status::FINISHED));
        $task = $queue->find($identifier);
        $this->assertEquals(Status::FINISHED, (string) $task->getStatus());

        return $queue;
    }

    /**
     *
     * @param RedisQueue $queue
     * @depends testUpdateTask
     */
    public function testDeleteTask(RedisQueue $queue)
    {
        $identifier = (string) self::$tasks[2]->getIdentifier();

        $queue->delete($identifier);

        $this->expectExceptionMessage(sprintf('Task %s does not exists', $identifier));
        $queue->find($identifier);
    }

    /**
     *
     * @param RedisQueue $queue
     * @return RedisQueue
     * @depends testUpdateTask
     */
    public function testRestoreTask(RedisQueue $queue): RedisQueue
    {
        $queue->restore();

        foreach ($queue->search() as $task) {
            $this->assertTrue(in_array((string) $task->getStatus(), [
                Status::WAITING,
                Status::FINISHED,
            ]));
        }

        return $queue;
    }

    /**
     *
     * @param RedisQueue $queue
     * @depends testRestoreTask
     */
    public function testFlushTask(RedisQueue $queue)
    {
        $queue->flush();
        $this->assertEmpty($queue->search());
    }
}
