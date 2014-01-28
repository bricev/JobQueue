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

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Libcast\JobQueue\Console\Command\UpstartCommand;

class StopUpstartCommand extends UpstartCommand
{
    protected function configure()
    {
        $this
            ->setName('upstart:stop')
            ->setDescription('Proxy task to stop workers via upstart')
        ;

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $workers = $this->getWorkerList($input);
        if (empty($workers)) {
            $output->writeln("There is no running worker.");
        }

        foreach ($workers as $name) {
            if ($this->ping($name)) {
                $process = $this->stop($name);
                $this->finishProcess($process, $output);
                $this->addLine("Worker $name stopped.");
            } else {
                $this->addLine("Worker $name not found or not started yet.");
            }
        }

        $output->writeln($this->getLines());
    }
}
