<?php

namespace Libcast\JobQueue\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Libcast\JobQueue\Exception\CommandException;
use Libcast\JobQueue\Command\JobQueueCommand;
use Libcast\JobQueue\Task\Task;

class DeleteJobQueueCommand extends JobQueueCommand
{
  protected function configure()
  {
    $this->setName('jobqueue:delete')->
            setDescription('Delete a Task')->
            addArgument('id', InputArgument::REQUIRED, 'Task Id');

    parent::configure();
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $queue = $this->getQueue();

    $task = $queue->getTask($input->getArgument('id'));

    $queue->remove($task);

    $this->addLine("Task $task has been removed from Queue.");

    $output->writeln($this->getLines());
  }
}