<?php

namespace JobQueue\Application\Console;

use JobQueue\Application\Utils\CommandTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class DeleteTask extends ManagerCommand
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

        $this->queue->delete($identifier);

        $this->formatInfoSection(sprintf('Task %s deleted', $identifier), $output);

        return 0;
    }
}
