<?php

namespace JobQueue\Application\Manager;

use JobQueue\Application\Utils\CommandTrait;
use JobQueue\Infrastructure\ServiceContainer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ShowTask extends Command
{
    use CommandTrait;

    public function configure(): void
    {
        $this
            ->setName('show')
            ->setDescription('Show a task information')
            ->addArgument('identifier', InputArgument::REQUIRED, 'Task UUID identifier')
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

        $this->formatTaskBlock($task, $output);
    }
}
