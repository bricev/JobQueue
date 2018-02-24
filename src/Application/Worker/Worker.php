<?php

namespace JobQueue\Application\Worker;

use Symfony\Component\Console\Application;

final class Worker extends Application
{
    public function __construct()
    {
        parent::__construct('worker', 1.0);

        $this->add(new Consume);
        $this->setDefaultCommand('consume', true);
    }
}
