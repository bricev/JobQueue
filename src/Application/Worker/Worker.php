<?php

namespace JobQueue\Application\Worker;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class Worker extends Application
{
    public function __construct()
    {
        parent::__construct('worker', 1.0);
    }

    /**
     *
     * @param InputInterface|null $input
     * @param OutputInterface|null $output
     * @return int
     */
    public function run(InputInterface $input = null, OutputInterface $output = null)
    {
        $this->add(new Consume);
        $this->setDefaultCommand('consume', true);

        return parent::run($input, $output);
    }
}
