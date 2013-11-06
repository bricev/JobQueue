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

use Symfony\Component\Process\Process;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Libcast\JobQueue\Console\Command\Command;
use Libcast\JobQueue\Exception\CommandException;

class UpstartCommand extends Command
{
    /**
     * @see Command
     */
    protected function configure()
    {

        parent::configure();
        $this->upstartDir = '/etc/init';
        $this->addOption('worker', 'w',
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'worker name, or list of workers (ex: -w worker1 -w worker2)'
        );

    }

    /**
     * Check if the upstart config file exists.
     *
     * @param  string $name The worker name
     * @return boolean      Wheter or not it exists
     */
    protected function workerConfIsInstalled($name)
    {
        return is_file($this->getWorkerConfPath($name));
    }

    /**
     * Build the upstart worker conf path
     *
     * @param  string $name The worker name
     * @return string       The upstart worker conf path
     */
    protected function getWorkerConfPath($name)
    {
        return "{$this->upstartDir}/{$this->getUpstartName($name)}.conf";
    }

    /**
     * Build the upstart service name, arbitrarily
     *
     * @param  string $name The worker name
     * @return string       The upstart service name
     */
    protected function getUpstartName($name)
    {
        return "libcast-jobqueue-{$name}";
    }

    /**
     * Returns the workers installed, filtered if user has used the -w option
     *
     * @param  InputInterface $input        User input from CLI
     * @param  Boolean        $installState true if we want installed workers, false otherwise
     *
     * @return array                        The list of workers
     */
    protected function getWorkerList(InputInterface $input, $installState = true)
    {
        $workers_in_conf   = array_keys($this->jobQueue['workers']);

        $workers = array();
        foreach ($workers_in_conf as $worker) {
            if ($installState === $this->workerConfIsInstalled($worker)) {
                $workers[] = $worker;
            }
        }
        $filtered  = $input->getOption('worker');

        if (!empty($filtered)) {
            $workers = array_intersect($workers, $filtered);
        }
        return $workers;
    }

    protected function start($worker)
    {
        $process = new Process("service {$this->getUpstartName($worker)} start");
        $process->run();
        return $process;
    }

    protected function stop($worker)
    {
        $process = new Process("service {$this->getUpstartName($worker)} stop");
        $process->run();
        return $process;
    }

    protected function status($worker)
    {
        $process = new Process("status {$this->getUpstartName($worker)}");
        $process->run();
        return $process;
    }

    protected function finishProcess(Process $process, OutputInterface $output)
    {
        if (!$process->isSuccessful()) {
            $output->writeln("<error>{$process->getErrorOutput()}</error>");
            return false;
        }
        $output->writeln("<info>{$process->getOutput()}</info>");
        return true;
    }

    protected function ping($worker)
    {
        $process = new Process("pgrep -c -f {$this->getUpstartName($worker)}");
        $process->run();
        if (!$process->isSuccessful()) {
            throw new CommandException($process->getErrorOutput());
        }
        return $process->getOutput() < 2 ? false : true;
    }
}
