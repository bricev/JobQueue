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
use Libcast\JobQueue\Console\OutputTable;

class InfoUpstartCommand extends UpstartCommand
{
    protected function configure()
    {
        $this
            ->setName('upstart:info')
            ->setDescription('Give info and status for workers')
        ;
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $workers  = array_keys($this->jobQueue['workers']);
        $filtered = $input->getOption('worker');
        if (!empty($filtered)) {
            $workers = array_intersect($workers, $filtered);
        }

        $output->writeln("");
        $output->writeln("Upstart status:");
        $output->writeln("");

        $table = new OutputTable;
        $table->addColumn('Name',           6,   OutputTable::RIGHT);
        $table->addColumn('Upstart Name',   12,  OutputTable::RIGHT);
        $table->addColumn('Status',         8,   OutputTable::LEFT);
        $table->addColumn('Profiles',       12,  OutputTable::LEFT);

        foreach ($workers as $worker) {
            if (!$this->workerConfIsInstalled($worker)) {
                $status = "Conf not installed";
            } else {
                $process = $this->status($worker);
                if (!$process->isSuccessful()) {
                    $status = "Conf is installed, but upstart may not be installed";
                } else {
                    $status = $process->getOutput();
                }
            }
            $table->addRow(array(
                'Name'          => $worker,
                'Upstart Name'  => $this->getUpstartName($worker),
                'Status'        => $status,
                'Profiles'      => implode(', ', $this->jobQueue['workers'][$worker]),
            ));
        }
        $output->writeln($table->getTable(true));
        $output->writeln("");
    }
}
