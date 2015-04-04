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
use Libcast\JobQueue\Exception\CommandException;
use Libcast\JobQueue\Task;

class ImportTaskCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('task:import')
            ->setDescription('Import a JSON file describing Tasks')
            ->addArgument('file', InputArgument::REQUIRED, 'JSON file')
        ;

        parent::configure();
    }

    /**
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws CommandException
     * @throws \Libcast\JobQueue\Exception\TaskException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $queue = $this->getQueue(); /* @var $queue \Libcast\JobQueue\Queue\QueueInterface */

        $file = $input->getArgument('file');
        if (!is_readable($file)) {
            throw new CommandException("JSON file '$file' does not exists or is not readable.");
        }

        if (!$items = json_decode(file_get_contents($file), true)) {
            throw new CommandException("File '$file' is malformed (must be JSON).");
        }

        foreach ($this->getTasks($items) as $task) { /* @var $task \Libcast\JobQueue\Task */
            $id = $queue->enqueue($task);
            $this->addLine("Task '$id' has been added to queue.");
        }

        $output->writeln($this->getLines());
    }

    /**
     *
     * @param array $items
     * @return array
     * @throws CommandException
     */
    protected function getTasks(array $items)
    {
        $tasks = [];
        foreach ($items as $item) {
            // Get Job
            if (!isset($item['job']) or !$job = $item['job']) {
                throw new CommandException('Missing Job.');
            } elseif (!class_exists($job)) {
                throw new CommandException("Job '$job' does not exists.");
            } else {
                $job = new $job;
            }

            // Get profile
            if (!isset($item['profile']) or !$profile = $item['profile']) {
                throw new CommandException('Missing Profile.');
            }

            // Get parameters
            $parameters = isset($item['parameters']) ? $item['parameters'] : [];

            // Generate the Task
            $task = new Task($job, $profile, $parameters);

            // Add children to the Task
            if (isset($item['children']) and $children = $item['children']) {
                foreach ($this->getTasks($children) as $child) { /* @var $child \Libcast\JobQueue\Task */
                    $task->addChild($child);
                }
            }

            $tasks[] = $task;
        }

        return $tasks;
    }
}
