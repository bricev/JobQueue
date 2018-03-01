<?php

namespace JobQueue\Tests\Domain\Task;

use JobQueue\Domain\Task\Profile;
use JobQueue\Domain\Task\Task;
use JobQueue\Tests\Domain\Job;
use PHPUnit\Framework\TestCase;

final class TaskTest extends TestCase
{
    public function testBadParameterName()
    {
        $this->expectExceptionMessage('All parameters must be named with a string key');

        $task = new Task(
            new Profile('test'),
            new Job\DummyJob,
            [
                'foo' => 'bar',
                'bar' => 'baz',
                2 => 'foo',
            ]
        );
    }

    public function testNonScalarParameterValue()
    {
        $this->expectExceptionMessage('Parameter array must be a scalar or null');

        new Task(
            new Profile('test'),
            new Job\DummyJob,
            [
                'int' => 123,
                'string' => 'foobar',
                'null' => null,
                'bool' => true,
                'array' => [],
            ]
        );
    }

    public function testParameterGetter()
    {
        $task = new Task(
            new Profile('test'),
            new Job\DummyJob,
            [
                'foo' => 'bar',
                'bar' => 'baz',
            ]
        );

        $this->assertTrue($task->hasParameter('foo'));
    }

    public function testNonExistentParameterGetter()
    {
        $this->expectExceptionMessage('Parameter "foo" does not exists');

        $task = new Task(
            new Profile('test'),
            new Job\DummyJob,
            [
                'bar' => 'baz',
            ]
        );

        $task->getParameter('foo');
    }

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
