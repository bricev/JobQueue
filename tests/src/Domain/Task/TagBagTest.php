<?php

namespace JobQueue\Tests\Domain\Task;

use JobQueue\Domain\Task\Profile;
use JobQueue\Domain\Task\Task;
use JobQueue\Tests\Domain\Job;
use PHPUnit\Framework\TestCase;

final class TagBagTest extends TestCase
{
    public function testBadTagValue()
    {
        $this->expectExceptionMessage('Tag value must be a string');

        new Task(
            new Profile('test'),
            new Job\DummyJob,
            [],
            [
                'foo',
                'bar',
                null,
            ]
        );
    }

    public function testHasTag()
    {
        $task = new Task(
            new Profile('test'),
            new Job\DummyJob,
            [],
            [
                'foo',
                'bar',
            ]
        );

        $this->assertTrue($task->hasTag('foo'));
    }

    public function testDoesNotHaveTag()
    {
        $task = new Task(
            new Profile('test'),
            new Job\DummyJob,
            [],
            [
                'bar',
            ]
        );

        $this->assertFalse($task->hasTag('foo'));
    }
}
