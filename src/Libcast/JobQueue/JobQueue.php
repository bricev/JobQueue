<?php

namespace Libcast\JobQueue;

class JobQueue extends \Pimple
{
    const VERSION = "0.3-dev";

    public function __construct(array $config = [])
    {
        foreach ($config as $key => $value) {
            $this[$key] = $value;
        }
    }
}
