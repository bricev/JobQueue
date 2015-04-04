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
use Libcast\JobQueue\Console\OutputTable;

class ShowQueueCommand extends Command
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('queue:show')
            ->setDescription('List tasks from the queue')
            ->addOption('profile',    'p', InputOption::VALUE_OPTIONAL, 'Filter by profile (eg. "high-cpu")', null)
            ->addOption('status',     's', InputOption::VALUE_OPTIONAL, 'Filter by status (pending|waiting|running|success|failed|finished)', null)
            ->addOption('sort-order', 'o', InputOption::VALUE_OPTIONAL, 'Sort by order asc|desc', 'asc')
            ->addOption('follow',     'f', InputOption::VALUE_NONE,     'Refresh screen, display Queue Tasks in real time')
        ;

    }

    /**
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
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

    /**
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \Libcast\JobQueue\Exception\CommandException
     */
    protected function listTasks(InputInterface $input, OutputInterface $output)
    {
        $queue = $this->getQueue();

        $tasks = $queue->getTasks(
            $input->getOption('profile'),
            $input->getOption('status'),
            $input->getOption('sort-order')
        );

        $count = count($tasks);

        $header  = $count ? "There is $count Task(s) in Queue" : 'There is no Task in Queue';
        $header .= $input->getOption('profile') ? sprintf(' having "%s" as profile', $input->getOption('profile')) : '';
        $header .= $input->getOption('status') ? sprintf(' having "%s" as status', $input->getOption('status')) : '';

        $this->addLine($header);
        $this->addLine();

        if ($count) {
            $table = new OutputTable;
            $table->addColumn('Id',       6,  OutputTable::RIGHT);
            $table->addColumn('Parent',   6,  OutputTable::RIGHT);
            $table->addColumn('Profile',  12, OutputTable::LEFT);
            $table->addColumn('Job',      25, OutputTable::LEFT);
            $table->addColumn('%',        4,  OutputTable::RIGHT);
            $table->addColumn('Status',   8,  OutputTable::LEFT);

            foreach ($tasks as $task) { /* @var $task \Libcast\JobQueue\Task */
                $job = (string) $task->getJob();

                $table->addRow([
                    'Id'      => $task->getId(),
                    'Parent'  => $task->getParentId(),
                    'Profile' => $task->getProfile(),
                    'Job'     => substr($job, strrpos($job, '\\') + 1),
                    '%'       => $queue->getProgress($task),
                    'Status'  => $task->getStatus(),
                ], $task->getStatus());
            }

            $output->getFormatter()->setStyle('pending',  new OutputFormatterStyle('white'));
            $output->getFormatter()->setStyle('waiting',  new OutputFormatterStyle('blue'));
            $output->getFormatter()->setStyle('running',  new OutputFormatterStyle('blue', 'cyan'));
            $output->getFormatter()->setStyle('failed',   new OutputFormatterStyle('red'));
            $output->getFormatter()->setStyle('success',  new OutputFormatterStyle('green'));
            $output->getFormatter()->setStyle('finished', new OutputFormatterStyle('green', null, ['bold']));

            $this->addLine($table->getTable(true));
        }
    }
}
