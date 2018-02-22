<?php

namespace JobQueue\Application\Manager;

use JobQueue\Application\Utils\CommandTrait;
use JobQueue\Domain\Task\Profile;
use JobQueue\Domain\Task\Task;
use JobQueue\Infrastructure\ServiceContainer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class AddTask extends Command
{
    use CommandTrait;

    public function configure(): void
    {
        $this
            ->setName('add')
            ->setDescription('Add a new task to the queue')
            ->addArgument('profile', InputArgument::REQUIRED, 'Profile name')
            ->addArgument('job', InputArgument::REQUIRED, 'Job class name')
            ->addArgument('parameters', InputArgument::IS_ARRAY, 'List of parameters (key:value)', [])
        ;
    }

    /**
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $jobName = $input->getArgument('job');

        $parameters = [];
        foreach ($input->getArgument('parameters') as $parameter) {
            list($name, $value) = explode(':', $parameter);
            $parameters[trim($name)] = trim($value);
        }

        $task = new Task(
            new Profile($input->getArgument('profile')),
            new $jobName,
            $parameters
        );

        ServiceContainer::getInstance()
            ->queue
            ->add($task);

        $this->formatTaskBlock($task, $output);
    }
}
