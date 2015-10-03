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
use Libcast\JobQueue\JobQueue;

class Command extends BaseCommand
{
    /**
     *
     * @var JobQueue
     */
    protected $jobQueue;

    /**
     *
     * @var array
     */
    protected $lines = [];

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
            $config = getcwd() . DIRECTORY_SEPARATOR . $config;
        }

        if (!is_file($config)) {
            throw new \InvalidArgumentException(sprintf('Configuration file "%s" does not exist', $config));
        }

        $this->jobQueue = require $config;
    }

    /**
     *
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
     * @return \Doctrine\Common\Cache\Cache
     */
    protected function getCache()
    {
        return $this->jobQueue['cache'];
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
     *
     * @param null $line
     */
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

    /**
     *
     * @return array
     */
    protected function getLines()
    {
        $this->lines[] = '';

        return $this->lines;
    }

    /**
     * Clear prompt lines cache
     *
     */
    protected function flushLines()
    {
        $this->lines = [];
    }
}
