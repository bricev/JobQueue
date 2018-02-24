<?php

namespace JobQueue\Application\Manager;

use JobQueue\Application\Utils\CommandTrait;
use JobQueue\Domain\Task\Status;
use JobQueue\Infrastructure\ServiceContainer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class DeleteTask extends Command
{
    use CommandTrait;

    public function configure()
    {
        $this
            ->setName('delete')
            ->setDescription('Delete a task')
            ->addArgument('identifier', InputArgument::REQUIRED, 'Task UUID identifier')
        ;
    }

    /**
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $identifier = $input->getArgument('identifier');

        ServiceContainer::getInstance()
            ->queue
            ->delete($identifier);

        $this->formatInfoSection(sprintf('Task %s deleted', $identifier), $output);

        return 0;
    }
}
