<?php

namespace JobQueue\Application\Worker;

use JobQueue\Application\Utils\CommandTrait;
use JobQueue\Domain\Task\Profile;
use JobQueue\Domain\Worker;
use JobQueue\Infrastructure\ServiceContainer;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class Consume extends Command
{
    use CommandTrait;

    public function configure(): void
    {
        $this
            ->setName('consume')
            ->setDescription('Consumes tasks from the queue')
            ->setHelp('Consumes tasks from a queue depending on one or multiple profiles. Each task is consumed by executing the corresponding job.')
            ->addArgument('profile', InputArgument::REQUIRED, 'Name of the profile to consume')
            ->addOption('name', 'w', InputOption::VALUE_OPTIONAL, 'Name of the worker')
        ;
    }

    /**
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $name = $input->getOption('name') ?: Uuid::uuid4();

        $profile = new Profile($input->getArgument('profile'));

        $this->formatInfoSection(sprintf('Worker %s is running...', $name), $output);

        $services = ServiceContainer::getInstance();
        $queue = $services->queue;
        $logger = isset($services->logger) ? $services->logger : null;

        (new Worker($name, $queue, $profile))->run($logger);

        $this->formatErrorSection(sprintf('Worker %s has hanged out!', $name), $output);
    }
}
