<?php

namespace Libcast\Job\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

use Libcast\Job\Command\JobCommand;
use Libcast\Job\Command\OutputTable;
use Libcast\Job\Task\Task;

class ListJobCommand extends JobCommand
{
  protected function configure()
  {
    $this->setName('list:task')->
            setDescription('List Tasks from the Queue')->
            addOption('sort-by',      'g',  InputOption::VALUE_OPTIONAL, 'Sort by (priority|profile|status)',   'priority')->
            addOption('order',        'o',  InputOption::VALUE_OPTIONAL, 'Order (asc|desc)',                    'desc')->
            addOption('priority',     'p',  InputOption::VALUE_OPTIONAL, 'Filter by priority (1, 2, ...)',      null)->
            addOption('profile',      'f',  InputOption::VALUE_OPTIONAL, 'Filter by profile (eg. "high-cpu")',  null)->
            addOption('status',       's',  InputOption::VALUE_OPTIONAL, 'Filter by status (pending|waiting|running|success|failed|finished)', null);
    
    parent::configure();
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    while (true)
    {
      $this->flushLines();

      $this->listTasks($input, $output);

      system('clear');

      $output->writeln($this->getLines());

      if (Task::STATUS_FINISHED === $input->getOption('status'))
      {
        break;
      }

      sleep(1);
    }
  }
  
  protected function listTasks(InputInterface $input, OutputInterface $output)
  {
    $queue = $this->getQueue($input);

    $tasks = $queue->getTasks($input->getOption('sort-by'), 
            $input->getOption('order'), 
            (int) $input->getOption('priority'), 
            $input->getOption('profile'), 
            $input->getOption('status'));

    $count = count($tasks);

    $header  = $count ? "There is $count Task(s) in Queue" : 'There is no Task in Queue';
    $header .= $input->getOption('priority') ? sprintf(' having "%d" as priority', $input->getOption('priority')) : '';
    $header .= $input->getOption('profile') ? sprintf(' having "%s" as profile', $input->getOption('profile')) : '';
    $header .= $input->getOption('status') ? sprintf(' having "%s" as status', $input->getOption('status')) : '';

    $this->addLine();
    $this->addLine($header);
    $this->addLine();

    if ($count)
    {
      $table = new OutputTable;
      $table->addColumn('Id',         10, OutputTable::RIGHT);
      $table->addColumn('Parent Id',  10, OutputTable::RIGHT);
      $table->addColumn('Priority',   3,  OutputTable::RIGHT);
      $table->addColumn('Profile',    25, OutputTable::LEFT);
      $table->addColumn('%',          4,  OutputTable::RIGHT);
      $table->addColumn('Status',     15, OutputTable::LEFT);
      
      foreach ($tasks as $task)
      {
        $table->addRow(array(
            'Id'          => $task->getId(),
            'Parent Id'   => $task->getParentId(),
            'Priority'    => $task->getOption('priority'),
            'Profile'     => $task->getOption('profile'),
            '%'           => $task->getProgress(false),
            'Status'      => $task->getStatus(),
        ), $task->getStatus());
      }
      
      $output->getFormatter()->setStyle('waiting',  new OutputFormatterStyle('blue'));
      $output->getFormatter()->setStyle('running',  new OutputFormatterStyle('blue', 'cyan', array('bold', 'blink')));
      $output->getFormatter()->setStyle('failed',   new OutputFormatterStyle('red'));
      $output->getFormatter()->setStyle('success',  new OutputFormatterStyle('green'));
      $output->getFormatter()->setStyle('finished', new OutputFormatterStyle('green', null, array('bold')));

      $this->addLine($table->getTable(true));
      $this->addLine();
    }
  }
}