<?php

/*
 * This file is part of Libcast JobQueue component.
 *
 * (c) Brice Vercoustre <brcvrcstr@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Libcast\JobQueue\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Libcast\JobQueue\Console\Command\Command;
use Libcast\JobQueue\Worker\Worker;

class RunWorkerCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('worker:run')
            ->setDescription('Run a worker')
            ->addArgument('worker', InputArgument::REQUIRED)
        ;
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $worker = new Worker(
            $input->getArgument('worker'),
            $this->jobQueue['queue'],
            $this->jobQueue['workers'][$input->getArgument('worker')],
            $this->jobQueue['logger']
        );
        $worker->run();
    }
}
