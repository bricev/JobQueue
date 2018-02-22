<?php

namespace JobQueue\Domain\Task;

use Ramsey\Uuid\Uuid;

final class Identifier
{
    /**
     *
     * @var string
     */
    private $value;

    /**
     *
     * @param string|null $value
     */
    public function __construct(string $value = null)
    {
        $this->value = $value ?: Uuid::uuid4();
    }

    /**
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
