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

        $env = getenv('JOBQUEUE_ENV') ?: 'dev';
        if (is_array($env)) {
            $env = array_shift($env);
        }

        return self::$name = $env;
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
