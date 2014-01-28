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
use Libcast\JobQueue\Console\Command\Command;
use Libcast\JobQueue\Task\Task;

class AddTaskCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('task:add')
            ->setDescription('Add a Task')
            ->addArgument('job',            InputArgument::REQUIRED,     'Job class namespace')
            ->addOption('parent-id',  'i',  InputOption::VALUE_OPTIONAL, 'Set parent Id (Eg. 123)', null)
            ->addOption('status',     's',  InputOption::VALUE_OPTIONAL, 'Set status (Eg. waiting)', Task::STATUS_PENDING)
            ->addOption('option',     'o',  InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Add an option (Eg. --option="profile: my_profile")', array())
            ->addOption('parameter',  'p',  InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Add a parameter (Eg. --parameter="bitrate: 1024")', array())
        ;

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $queue = $this->getQueue();
        /* @var $queue \Libcast\JobQueue\Queue\QueueInterface */

        $job = (string) $input->getArgument('job');

        $task = new Task(new $job,
                $this->optionToArray($input->getOption('option')),
                $this->optionToArray($input->getOption('parameter')));

        if ($input->hasOption('parent-id')) {
            $task->setParentId($input->getOption('parent-id'));
        }

        if ($input->hasOption('status')) {
            $task->setStatus($input->getOption('status'));
        }

        $queue->add($task);

        $this->addLine("Task '$task' has been added to queue.");
        $output->writeln($this->getLines());
    }

    protected function optionToArray($input)
    {
        $output = array();
        foreach ($input as $values) {
            if (count($value = explode(':', $values)) > 1) {
                $output[trim($value[0])] = trim($value[1]);
            } else {
                throw new CommandException('An error occured with one array option.');
            }
        }

        return $output;
    }
}
