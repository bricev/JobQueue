<?php

namespace JobQueue\Infrastructure;

class Environment
{
    /**
     *
     * @var string
     */
    protected static $name;

    /**
     *
     * @param string $name
     */
    public static function setName(string $name)
    {
        if (self::$name) {
            throw new \RuntimeException(sprintf('Environment has already been set as "%s"', self::$name));
        }

        self::$name = $name;
    }

    /**
     *
     * @return string
     */
    public static function getName(): string
    {
        if (self::$name) {
            return self::$name;
        }

        return self::$name = getenv('JOBQUEUE_ENV') ?: 'dev';
    }

    /**
     *
     * @return bool
     */
    public static function isProd(): bool
    {
        return 'prod' === self::getName();
    }
}
