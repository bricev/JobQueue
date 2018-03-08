<?php

namespace JobQueue\Application\Console;

use JobQueue\Application\Utils\CommandTrait;
use JobQueue\Domain\Task\Profile;
use JobQueue\Domain\Worker\Worker;
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

    public function configure()
    {
        $this
            ->setName('consume')
            ->setDescription('Consumes tasks from the queue')
            ->setHelp("Consumes tasks from a queue depending on one or multiple profiles.  \nEach task is consumed by executing the corresponding job.\n ")
            ->addArgument('profile', InputArgument::REQUIRED, 'Name of the profile to consume')
            ->addOption('name', 'w', InputOption::VALUE_OPTIONAL, 'Name of the worker')
            ->addOption('quantity', 'x', InputOption::VALUE_OPTIONAL, 'Quantity of tasks to consume')
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
        $services = ServiceContainer::getInstance();

        $worker = new Worker(
            $name = $input->getOption('name') ?: (string) Uuid::uuid4(),
            $services->queue,
            $profile = new Profile($input->getArgument('profile')),
            $services->dispatcher
        );

        if (isset($services->logger)) {
            $worker->setLogger($services->logger);
        }

        $this->formatInfoSection(sprintf('Worker %s handles "%s" tasks...', $name, $profile), $output);

        $worker->consume($quantity = (int) $input->getOption('quantity') ?: null);

        if ($quantity > 0) {
            $this->formatInfoSection(sprintf('Worker %s is done.', $name), $output);
        } else {
            $this->formatErrorSection(sprintf('Worker %s has hanged out!', $name), $output);
        }

        return 0;
    }
}
