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
        if (!preg_match('/^[_a-z0-9-]+$/', $name)) {
            throw new \RuntimeException('Profile name only allows lowercase alphanumerical, dash and underscore characters');
        }

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
