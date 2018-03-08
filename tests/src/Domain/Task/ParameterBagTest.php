<?php

namespace JobQueue\Tests\Domain\Task;

use JobQueue\Domain\Task\Profile;
use JobQueue\Domain\Task\Task;
use JobQueue\Tests\Domain\Job;
use PHPUnit\Framework\TestCase;

final class ParameterBagTest extends TestCase
{
    public function testBadParameterName()
    {
        $this->expectExceptionMessage('The key must be a string');

        new Task(
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
        $this->expectExceptionMessage('The "erroneous" value must be a scalar or null');

        new Task(
            new Profile('test'),
            new Job\DummyJob,
            [
                'int' => 123,
                'string' => 'foobar',
                'null' => null,
                'bool' => true,
                'erroneous' => [],
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
        $this->expectExceptionMessage('There is no value for "foo" key');

        $task = new Task(
            new Profile('test'),
            new Job\DummyJob,
            [
                'bar' => 'baz',
            ]
        );

        $task->getParameter('foo');
    }
}
