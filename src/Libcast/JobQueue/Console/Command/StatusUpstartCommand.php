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

class StatusUpstartCommand extends UpstartCommand
{
    protected function configure()
    {
        $this
            ->setName('upstart:status')
            ->setDescription('Proxy task to get workers\' status via upstart')
        ;

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $workers = $this->getWorkerList($input);
        foreach ($workers as $name) {
            $process = $this->status($name);
            $this->finishProcess($process, $output);
        }
    }
}
