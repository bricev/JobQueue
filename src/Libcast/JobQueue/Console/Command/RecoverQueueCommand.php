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

use Libcast\JobQueue\Task;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class RecoverQueueCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('queue:recover')
            ->setDescription('Requeue all running and failed Tasks')
            ->addOption('running', 'r', InputOption::VALUE_NONE, 'Also recover running tasks')
        ;

        parent::configure();
    }

    /**
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dialog = $this->getHelperSet()->get('dialog');

        $validate = $dialog->select($output, 'Do you really want to recover failed tasks?', [
            'no'  => 'Cancel',
            'yes' => 'Validate',
        ], 'no');

        if ('yes' === $validate) {
            $queue = $this->getQueue();

            $tasks = $queue->getTasks(null, Task::STATUS_FAILED);

            if ($input->getOption('running')) {
                $tasks = array_merge($tasks, $queue->getTasks(null, Task::STATUS_RUNNING));
            }

            foreach ($tasks as $task) { /* @var $task \Libcast\JobQueue\Task */
                $task->setStatus(Task::STATUS_WAITING);
                $queue->update($task);
            }

            $this->addLine('The queue has been recovered.');
        } else {
            $this->addLine('Cancelled.');
        }

        $output->writeln($this->getLines());
    }
}
