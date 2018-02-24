<?php

namespace JobQueue\Tests\Task;

use JobQueue\Domain\Task\Identifier;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class IdentifierTest extends TestCase
{
    public function testUuidConstruction()
    {
        $identifier = new Identifier;
        $this->assertTrue(Uuid::isValid($identifier));
    }
}
