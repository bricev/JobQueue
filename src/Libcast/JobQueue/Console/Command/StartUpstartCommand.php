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

class StartUpstartCommand extends UpstartCommand
{
    protected function configure()
    {
        $this
            ->setName('upstart:start')
            ->setDescription('Proxy task to start workers via upstart')
        ;

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $workers = $this->getWorkerList($input);
        foreach ($workers as $name) {
            $this->jobQueue['queue']->reboot($this->jobQueue['workers'][$name]);
            if (!$this->ping($name)) {
                $process = $this->start($name);
                $this->finishProcess($process, $output);
            }
        }
    }
}
