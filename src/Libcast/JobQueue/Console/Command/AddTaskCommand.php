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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Libcast\JobQueue\Exception\CommandException;
use Libcast\JobQueue\Task;

class AddTaskCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('task:add')
            ->setDescription('Add a Task')
            ->addArgument('job',          InputArgument::REQUIRED,     'Job class namespace')
            ->addOption('parent-id', 'i', InputOption::VALUE_OPTIONAL, 'Set parent Id (Eg. 123)', null)
            ->addOption('profile',   'p', InputOption::VALUE_REQUIRED, 'Set profile (Eg. "high-cpu")', null)
            ->addOption('status',    's', InputOption::VALUE_REQUIRED, 'Set status (Eg. waiting)', Task::STATUS_PENDING)
            ->addOption('parameter', 't', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Add a parameter (Eg. --parameter="bitrate: 1024")', [])
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

        $job = (string) $input->getArgument('job');
        if (!class_exists($job)) {
            throw new CommandException("Job class '$job' does not exists.");
        }

        $parameters = [];
        foreach ($input->getOption('parameter') as $parameter) {
            list($key, $value) = explode(':', $parameter);
            if (!$key or !$value) {
                throw new CommandException("The '$parameter' parameter is malformed.");
            }

            $parameters[trim($key)] = trim($value);
        }

        $task = new Task(
            new $job,
            $input->getOption('profile'),
            $parameters
        );

        if ($parent = $queue->getTask($input->hasOption('parent-id'))) {
            $parent->addChild($task);
            $queue->update($parent);

            $task->setParentId($parent->getId());
        }

        if ($status = $input->hasOption('status')) {
            $task->setStatus($status);
        }

        $id = $queue->enqueue($task);

        $this->addLine("Task '$id' has been added to queue.");
        $output->writeln($this->getLines());
    }
}
