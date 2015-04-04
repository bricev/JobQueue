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

use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class Command extends BaseCommand
{
    protected $lines = array('');

    /**
     * @see Command
     */
    protected function configure()
    {
        $this->getDefinition()->addArgument(
            new InputArgument('config', InputArgument::OPTIONAL, 'The configuration')
        );
    }

    /**
     *
     * @return \Libcast\JobQueue\Queue\QueueInterface
     */
    protected function getQueue()
    {
        return $this->jobQueue['queue'];
    }

    /**
     *
     * @return \Psr\Log\LoggerInterface
     */
    protected function getLogger()
    {
        return $this->jobQueue['logger'];
    }

    /**
     * @see Command
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $config = $input->getArgument('config');
        if (is_null($config)) {
            $config = getcwd() . '/config/jobqueue.php';
        }
        $filesystem = new Filesystem();

        if (!$filesystem->isAbsolutePath($config)) {
            $config = getcwd().'/'.$config;
        }
        // die(getcwd());
        if (!is_file($config)) {
            throw new \InvalidArgumentException(sprintf('Configuration file "%s" does not exist.', $config));
        }

        $this->jobQueue = require $config;
    }

    protected function addLine($line = null)
    {
        if (!$line) {
            $line = '';
        }

        if (is_array($line)) {
            $this->lines = array_merge($this->lines, $line);
            return;
        }

        $this->lines[] = $line;
    }

    protected function getLines()
    {
        $this->lines[] = '';

        return $this->lines;
    }

    protected function flushLines()
    {
        $this->lines = array('');
    }
}
