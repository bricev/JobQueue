<?php

namespace JobQueue\Tests\Domain\Task;

use JobQueue\Domain\Task\Status;
use PHPUnit\Framework\TestCase;

final class StatusTest extends TestCase
{
    public function testBadStatusName()
    {
        $this->expectException(\RuntimeException::class);

        new Status('unknown');
    }

    public function testStatusListing()
    {
        $reflexion = new \ReflectionClass(Status::class);

        $statuses = $reflexion->getConstants();
        foreach ($statuses as $status) {
            $this->assertTrue(in_array($status, Status::listStatus()));
        }
    }
}
