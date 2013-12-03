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

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Libcast\JobQueue\Console\Command\Command;
use Libcast\JobQueue\Console\OutputTable;

class ShowQueueCommand extends Command
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('queue:show')
            ->setDescription('List jobs from the queue')
            ->addOption('sort-by',  't',  InputOption::VALUE_OPTIONAL,  'Sort by (priority|profile|status)', 'priority')
            ->addOption('order',    'o',  InputOption::VALUE_OPTIONAL,  'Order (asc|desc)', 'desc')
            ->addOption('priority', 'p',  InputOption::VALUE_OPTIONAL,  'Filter by priority (1, 2, ...)', null)
            ->addOption('profile',  'l',  InputOption::VALUE_OPTIONAL,  'Filter by profile (eg. "high-cpu")', null)
            ->addOption('status',   's',  InputOption::VALUE_OPTIONAL,  'Filter by status (pending|waiting|running|success|failed|finished)', null)
            ->addOption('follow',   'f',  InputOption::VALUE_NONE,      'Refresh screen, display Queue Tasks in real time')
        ;

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        while (true) {
            $this->listTasks($input, $output);

            if ($input->getOption('follow')) {
                system('clear');
            }

            $output->writeln($this->getLines());

            if ($input->getOption('follow')) {
                $this->flushLines();
                sleep(1);
                continue;
            }

            break;
        }
    }

    protected function listTasks(InputInterface $input, OutputInterface $output)
    {
        $tasks = $this->jobQueue['queue']->getTasks(
                $input->getOption('sort-by'),
                $input->getOption('order'),
                (int) $input->getOption('priority'),
                $input->getOption('profile'),
                $input->getOption('status')
        );

        $count = count($tasks);

        $header  = $count ? "There is $count Task(s) in Queue" : 'There is no Task in Queue';
        $header .= $input->getOption('priority') ? sprintf(' having "%d" as priority', $input->getOption('priority')) : '';
        $header .= $input->getOption('profile') ? sprintf(' having "%s" as profile', $input->getOption('profile')) : '';
        $header .= $input->getOption('status') ? sprintf(' having "%s" as status', $input->getOption('status')) : '';

        $this->addLine($header);
        $this->addLine();

        if ($count) {
            $table = new OutputTable;
            $table->addColumn('Id',       6,  OutputTable::RIGHT);
            $table->addColumn('Parent',   6,  OutputTable::RIGHT);
            $table->addColumn('Pty',      3,  OutputTable::RIGHT);
            $table->addColumn('Profile',  12, OutputTable::LEFT);
            $table->addColumn('Job',      22, OutputTable::LEFT);
            $table->addColumn('%',        4,  OutputTable::RIGHT);
            $table->addColumn('Status',   8,  OutputTable::LEFT);

            foreach ($tasks as $task) {
                $jobArray = explode('\\', $task->getJob());
                $job = $jobArray[count($jobArray) ? count($jobArray) - 1 : 0];

                $table->addRow(array(
                    'Id'      => $task->getId(),
                    'Parent'  => $task->getParentId(),
                    'Pty'     => $task->getOption('priority'),
                    'Profile' => $task->getOption('profile'),
                    'Job'     => $job,
                    '%'       => $task->getProgress(true),
                    'Status'  => $task->getStatus(),
                ), $this->jobQueue['queue']->getTaskStatus($task->getId()));
            }

            $output->getFormatter()->setStyle('pending',  new OutputFormatterStyle('white'));
            $output->getFormatter()->setStyle('waiting',  new OutputFormatterStyle('blue'));
            $output->getFormatter()->setStyle('running',  new OutputFormatterStyle('blue', 'cyan', array('bold', 'blink')));
            $output->getFormatter()->setStyle('failed',   new OutputFormatterStyle('red'));
            $output->getFormatter()->setStyle('success',  new OutputFormatterStyle('green'));
            $output->getFormatter()->setStyle('finished', new OutputFormatterStyle('green', null, array('bold')));

            $this->addLine($table->getTable(true));
        }
    }
}
