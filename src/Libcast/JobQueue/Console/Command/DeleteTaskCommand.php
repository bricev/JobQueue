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
use Symfony\Component\Console\Output\OutputInterface;

class DeleteTaskCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('task:delete')
            ->setDescription('Delete a Task')
            ->addArgument('id', InputArgument::REQUIRED, 'Task Id')
        ;

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $queue = $this->getQueue();
        
        $task = $queue->getTask($input->getArgument('id'));

        $queue->delete($task);

        $this->addLine("Task $task has been deleted from the Queue.");

        $output->writeln($this->getLines());
    }
}
