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
            addArgument('action', InputArgument::REQUIRED, 'reboot|flush')->
            addOption('profile', 'p', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'List of profiles');

    parent::configure();
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $action = $input->getArgument('action');

    $profiles = $input->getOption('profile') ? 
            $input->getOption('profile') : 
            $this->getApplication()->getParameter('profiles');

    $dialog = $this->getHelperSet()->get('dialog');

    $validate = $dialog->select($output, "Do you really want to $action the Queue?", array(
        'no'  => 'Cancel',
        'yes' => 'Validate (can not be undone)',
    ), 'no');

    if ('yes' === $validate)
    {
      switch ($action)
      {
        case 'reboot':
          $this->getQueue()->reboot(is_array($profiles) ? $profiles : array());
          $this->addLine('The Queue has been rebooted.');
          break;

        case 'flush':
          $this->getQueue()->flush();
          $this->addLine('The Queue is empty.');
          break;

        default :
          throw new CommandException("The action '$action' is not permitted.");
      }
    }
    else
    {
      $this->addLine('Cancel.');
    }

    $output->writeln($this->getLines());
  }
}