<?php

namespace Libcast\JobQueue;

class JobQueue extends \Pimple
{
    const VERSION = "v1.1.0";

    /**
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        // Set null value for optional attributes
        $this['cache'] = null;
        $this['logger'] = null;

        foreach ($config as $key => $value) {
            $this[$key] = $value;
        }
    }
}
