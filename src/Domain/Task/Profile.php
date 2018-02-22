<?php

namespace JobQueue\Domain\Task;

final class Profile
{
    /**
     *
     * @var string
     */
    private $name;

    /**
     *
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     *
     * @return string
     */
    public function __toString()
    {
        return $this->name;
    }
}
