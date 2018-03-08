<?php

namespace JobQueue\Domain\Task;

final class Status
{
    const WAITING  = 'waiting',
          RUNNING  = 'running',
          FINISHED = 'finished',
          FAILED   = 'failed';

    /**
     *
     * @var
     */
    private $value;

    /**
     *
     * @param $value
     * @throws \Exception
     */
    public function __construct($value)
    {
        if (!in_array($value, self::listStatus(), true)) {
            throw new \RuntimeException(sprintf('Status "%s" does not exists', $value));
        }

        $this->value = $value;
    }

    /**
     *
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     *
     * @return array
     */
    public static function listStatus(): array
    {
        return [
            self::WAITING,
            self::RUNNING,
            self::FINISHED,
            self::FAILED,
        ];
    }

    /**
     *
     * @return mixed
     */
    public function __toString()
    {
        return $this->value;
    }
}
