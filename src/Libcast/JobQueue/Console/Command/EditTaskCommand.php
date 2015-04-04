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
use Libcast\JobQueue\Console\OutputTable;

class EditTaskCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('task:edit')
            ->setDescription('Edit a Task')
            ->addArgument('id',           InputArgument::REQUIRED,     'Task Id')
            ->addOption('parent-id', 'i', InputOption::VALUE_OPTIONAL, 'Set parent Id (Eg. 123)', null)
            ->addOption('profile',   'p', InputOption::VALUE_OPTIONAL, 'Set profile (eg. "high-cpu")', null)
            ->addOption('status',    's', InputOption::VALUE_OPTIONAL, 'Set status (pending|waiting|running|success|failed|finished)', null)
        ;

        parent::configure();
    }

    /**
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \Libcast\JobQueue\Exception\CommandException
     * @throws \Libcast\JobQueue\Exception\TaskException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $queue = $this->getQueue();
        $task = $queue->getTask($input->getArgument('id'));

        $update = false;

        if ($input->getOption('parent-id')) {
            $task->setParentId((int) $input->getOption('parent-id'));
            $update = true;
        }

        if ($input->getOption('profile')) {
            $task->setProfile($input->getOption('profile'));
            $update = true;
        }

        if ($input->getOption('status')) {
            $task->setStatus($input->getOption('status'));
            $update = true;
        }

        if ($update) {
            $header = "Task '$task' has been updated.";
            $queue->update($task);
        } else {
            $header = "Nothing to update on Task '$task'.";
        }

        $table = new OutputTable;
        $table->addColumn('Key',    15, OutputTable::RIGHT);
        $table->addColumn('Value',  25, OutputTable::LEFT);

        $table->addRow([
            'Key'   => 'Id',
            'Value' => $task->getId(),
        ]);

        $table->addRow([
            'Key'   => 'Parent Id',
            'Value' => $task->getParentId(),
        ]);

        if ($count = $task->countChildren()) {
            $table->addRow([
                'Key'   => 'Children',
                'Value' => $count,
            ]);
        }

        $table->addRow([
            'Key'   => 'Created at',
            'Value' => $task->getCreatedAt('r'),
        ]);

        $table->addRow([
            'Key'   => 'Job',
            'Value' => (string) $task->getJob(),
        ]);

        $table->addRow([
            'Key'   => 'Profile',
            'Value' => $task->getProfile(),
        ]);

        $table->addRow([
            'Key'   => 'Status',
            'Value' => $task->getStatus(),
        ]);

        $table->addRow([
            'Key'   => 'Progress',
            'Value' => $queue->getProgress($task),
        ]);

        foreach ($task->getParameters() as $key => $value) {
            $table->addRow([
                'Key'   => ucfirst($key),
                'Value' => $value,
            ]);
        }

        $this->addLine($header);
        $this->addLine();
        $this->addLine($table->getTable());

        $output->writeln($this->getLines());
    }
}
