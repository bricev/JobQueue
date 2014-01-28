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

/**
 * Console command that write workers config files in `/etc/init/`
 * It requires upstart (default on Ubuntu).
 *
 * @author  Corentin Merot <cmerot@themarqueeblink.com>
 */
class InstallUpstartCommand extends UpstartCommand
{
    /**
     * @{inherit}
     */
    protected function configure()
    {
        $this
            ->setName('upstart:install')
            ->setDescription('Install workers\' conf in /etc/init/')
        ;

        parent::configure();
    }

    /**
     * @{inherit}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // We need the full path to the config file to generate the parameter
        // to lauch the upstart service.
        $this->configFilePath = realpath($input->getArgument('config'));

        if (!$this->upstartDir
                || !is_dir($this->upstartDir)
                || !is_writable($this->upstartDir)) {
            $output->writeln("<error>'{$this->upstartDir}' do not exist or is not writable. You may try with sudo or install upstart (at your own risks).</error>");
            return;
        }

        $installState = false;
        $workers = $this->getWorkerList($input, $installState);
        if (empty($workers)) {
            $output->writeln('<comment> No uninstalled worker found. Nothing done. </comment>');
        }
        foreach ($workers as $name) {
            $this->install($name, $output);
        }
    }

    /**
     * Write the /etc/init/libcast-jobqueue-WORKER_NAME.conf file.
     * Require write privilege in `/etc/init/`.
     *
     * @param  string          $worker The worker name
     * @param  OutputInterface $output The output interface
     *
     * @return Boolean                 True if file is written, false otherwise
     */
    protected function install($worker, OutputInterface $output)
    {
        $console = realpath($_SERVER['PHP_SELF']);
        $command = "worker:run $worker {$this->configFilePath}";

        $conf = $this->getWorkerConfPath($worker);
        if (is_file($conf)) {
            $output->writeln("<error>  File `{$conf}` already exists. Manually delete it and relaunch the command.  </error>");
            return false;
        }

        $fh = fopen($conf, 'w+');
        fwrite($fh, implode(PHP_EOL, array(
            'start on runlevel 2',
            'stop on runlevel [!2]',
            'respawn',
            'respawn limit 2 10',
            "exec sudo -u www-data $console $command",
        )));
        fclose($fh);
        $output->writeln("<info>  File `{$conf}` installed.  </info>");
        return true;
    }

}
