<?php

namespace JobQueue\Tests;

use JobQueue\Domain\Task\Profile;
use JobQueue\Domain\Task\Task;
use PHPUnit\Framework\TestCase;

final class TaskTest extends TestCase
{
    public function testSerialization()
    {
        $task = new Task(
            new Profile('dummy'),
            new DummyJob
        );

        $serializedTask = serialize($task);
        $this->assertTrue(is_string($serializedTask));

        $unserializedTask = unserialize($serializedTask);
        $this->assertInstanceOf(Task::class, $unserializedTask);

        $jsonSerializedTask = json_encode($unserializedTask);
        $this->assertTrue(is_string($serializedTask));
    }
}
