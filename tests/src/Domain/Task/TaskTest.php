<?php

namespace JobQueue\Tests\Domain\Task;

use JobQueue\Domain\Task\Profile;
use JobQueue\Domain\Task\Task;
use JobQueue\Tests\Domain\Job;
use PHPUnit\Framework\TestCase;

final class TaskTest extends TestCase
{
    public function testSerialization()
    {
        $task = new Task(
            new Profile('test'),
            new Job\DummyJob
        );

        $serializedTask = serialize($task);
        $this->assertTrue(is_string($serializedTask));

        $unserializedTask = unserialize($serializedTask);
        $this->assertInstanceOf(Task::class, $unserializedTask);

        $jsonSerializedTask = json_encode($unserializedTask);
        $this->assertTrue(is_string($jsonSerializedTask));
    }

    public function testHumanReadableJobName()
    {
        $task = new Task(
            new Profile('test'),
            new Job\DummyJob
        );
        $this->assertEquals('dummy', $task->getJobName(true));

        $complexNameJob = new Task(
            new Profile('test'),
            new Job\ComplexTOSnakeCASEJob
        );
        $this->assertEquals('complex_to_snake_case', $complexNameJob->getJobName(true));
    }
}
