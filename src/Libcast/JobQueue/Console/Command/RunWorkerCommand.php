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
use Libcast\JobQueue\Worker;

class RunWorkerCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('worker:run')
            ->setDescription('Run a worker')
            ->addArgument('profile', InputArgument::REQUIRED)
        ;
        parent::configure();
    }

    /**
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $worker = new Worker($input->getArgument('profile'),
            $this->getQueue(),
            $this->getCache(),
            $this->getLogger());

        $worker->run();
    }
}
