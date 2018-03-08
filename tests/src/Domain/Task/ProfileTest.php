<?php

namespace JobQueue\Tests\Domain\Task;

use JobQueue\Domain\Task\Profile;
use PHPUnit\Framework\TestCase;

final class ProfileTest extends TestCase
{
    public function testBadProfileName()
    {
        $this->expectException(\RuntimeException::class);

        new Profile('Bad> <character');
    }
}
