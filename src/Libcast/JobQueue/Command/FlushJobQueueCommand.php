<?php

namespace Libcast\JobQueue\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Libcast\JobQueue\Exception\CommandException;
use Libcast\JobQueue\Command\JobQueueCommand;

class FlushJobQueueCommand extends JobQueueCommand
{
  protected function configure()
  {
    $this->setName('jobqueue:flush')->
            setDescription('Empty the Queue');

    parent::configure();
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $dialog = $this->getHelperSet()->get('dialog');

    $validate = $dialog->select($output, 'Do you really want to flush the Queue?', array(
        'no'  => 'Cancel',
        'yes' => 'Empty the Queue (can not be undone)',
    ), 'no');

    if ('yes' === $validate)
    {
      $this->getQueue()->flush();

      $this->addLine("The Queue is empty.");
    }
    else
    {
      $this->addLine("Cancel.");
    }
    
    $output->writeln($this->getLines());
  }
}