<?php

namespace Libcast\JobQueue\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Libcast\JobQueue\Exception\CommandException;
use Libcast\JobQueue\Command\JobQueueCommand;

class QueueJobQueueCommand extends JobQueueCommand
{
  protected function configure()
  {
    $this->setName('jobqueue:queue')->
            setDescription('Control Queue')->
            addArgument('action', InputArgument::REQUIRED, 'stop|start|restart')->
            addOption('profile',  'p', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'List of profiles');

    parent::configure();
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $profiles = $input->getOption('profile') ? $input->getOption('profile') : $this->getApplication()->getParameter('profiles');
    switch ($input->getArgument('action'))
    {
      case 'reboot':
        $this->getQueue()->reboot(is_array($profiles) ? $profiles : array());
        break;

      default :
        throw new CommandException('This action is not permitted.');
    }

    $output->writeln($this->getLines());
  }
}