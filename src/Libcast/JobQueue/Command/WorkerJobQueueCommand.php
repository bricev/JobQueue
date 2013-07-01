<?php

/*
 * This file is part of Libcast JobQueue component.
 *
 * (c) Brice Vercoustre <brcvrcstr@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE file 
 * that was distributed with this source code.
 */

namespace Libcast\JobQueue\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Libcast\JobQueue\Exception\CommandException;
use Libcast\JobQueue\Command\JobQueueCommand;

class WorkerJobQueueCommand extends JobQueueCommand
{
    protected function configure()
    {
        $this->
                setName('jobqueue:worker')->
                setDescription('Control local Workers')->
                addArgument('action', InputArgument::REQUIRED, 'stop|start|restart')->
                addOption('profile', 'p', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'List of profiles');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $action = $input->getArgument('action');

        if (!$workers = $this->getWorkers()) {
            throw new CommandException("There is no Worker to $action.");
        }

        $profiles = $input->getOption('profile') ? $input->getOption('profile') : $this->getApplication()->getParameter('profiles');

        $this->addLine(ucfirst($action).' Workers:');

        foreach ($workers as $worker)
        {
            $worker_name = pathinfo($worker, PATHINFO_FILENAME);
            $worker_path = $worker;

            if ($this->controlWorker($action, $worker_name, $worker_path, $profiles)) {
                $this->addLine("  * $worker_name ($worker_path)");
            } else {
                $this->addLine("  * impossible to $action $worker_name");
            }
        }

        $output->writeln($this->getLines());
    }

    protected function controlWorker($action, $worker_name, $worker_path, $profiles)
    {
        switch ($action) {
            case 'stop':
                return $this->stopWorker($worker_name, $worker_path);

            case 'restart':
                $this->stopWorker($worker_name, $worker_path);

            case 'start':
                $this->getQueue()->reboot(is_array($profiles) ? $profiles : array());
                return $this->startWorker($worker_name, $worker_path);

            default : 
                throw new CommandException("Action '$action' can't be executed.");
        }
    }

    protected function startWorker($worker_name, $worker_path)
    {
        if ($this->pingWorker($worker_path)) {
            return false;
        }

        return $this->controlUpstart('start', $worker_name, $worker_path);
    }

    protected function stopWorker($worker_name, $worker_path)
    {
        if (!$this->pingWorker($worker_path)) {
            return false;
        }

        return $this->controlUpstart('stop', $worker_name, $worker_path);
    }

    protected function controlUpstart($action, $worker_name, $worker_path)
    {
        if (!in_array($action, array(
            'stop',
            'start',
        ))) {
            throw new CommandException("Action '$action' does not exists.");
        }

        if (!$upstart_name = $this->getUpstart($worker_name, $worker_path)) {
            throw new CommandException("Impossible to find an upstart job for '$worker_name'.");
        }

        return false !== @system("service $upstart_name $action > /dev/null 2> /dev/null");
    }

    protected function getUpstart($worker_name, $worker_path)
    {
        $upstart_dir = realpath('/etc/init/');
        if (!$upstart_dir || !is_dir($upstart_dir) || !is_readable($upstart_dir)) {
            throw new CommandException('This command only works on Ubuntu.');
        }

        if (!is_file($upstart_path = "$upstart_dir/$worker_name.conf")) {
            $upstart = fopen($upstart_path, 'a+');

            fwrite($upstart,
                    'start on runlevel 2'.PHP_EOL.
                    'stop on runlevel [!2]'.PHP_EOL.
                    'respawn'.PHP_EOL.
                    'respawn limit 2 10'.PHP_EOL.
                    "exec sudo -u www-data php5 -f $worker_path");

            fclose($upstart);
        }

        return is_file($upstart_path) ? pathinfo($upstart_path, PATHINFO_FILENAME) : null;
    }

    protected function pingWorker($worker_path)
    {
        exec("pgrep -c -f $worker_path", $count);

        return $count[0] < 2 ? false : true;
    }

    protected function getWorkers()
    {
        if (!$workers = $this->getApplication()->getParameter('workers')) {
            throw new CommandException('There is no Worker listed from CLI configuration.');
        }

        foreach ($workers as $k => $v) {
            if (!file_exists($v) || !is_file($v)) {
                unset($workers[$k]);
            }
        }

        return $workers;
    }
}