<?php

namespace JobQueue\Application\Manager;

use JobQueue\Application\Utils\CommandTrait;
use JobQueue\Domain\Task\Status;
use JobQueue\Infrastructure\ServiceContainer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class EditTask extends Command
{
    use CommandTrait;

    public function configure(): void
    {
        $this
            ->setName('edit')
            ->setDescription('Edit a task status')
            ->addArgument('identifier', InputArgument::REQUIRED, 'Task UUID identifier')
            ->addArgument('status', InputArgument::REQUIRED, 'The new status')
        ;
    }

    /**
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $queue = ServiceContainer::getInstance()->queue;

        $task = $queue->find($input->getArgument('identifier'));

        $queue->updateStatus($task, new Status($input->getArgument('status')));

        $this->formatTaskBlock($task, $output);
    }
}
