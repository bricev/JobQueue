<?php

namespace JobQueue\Application\Console;

use JobQueue\Application\Utils\CommandTrait;
use JobQueue\Domain\Task\Profile;
use JobQueue\Domain\Task\Task;
use JobQueue\Infrastructure\ServiceContainer;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class AddTask extends ManagerCommand
{
    use CommandTrait;

    public function configure()
    {
        $this
            ->setName('add')
            ->setDescription('Add a new task to the queue')
            ->addArgument('profile', InputArgument::REQUIRED, 'Profile name')
            ->addArgument('job', InputArgument::REQUIRED, 'Job class name')
            ->addArgument('parameters', InputArgument::IS_ARRAY, 'List of parameters (key:value)', [])
            ->addOption('tags', 't', InputOption::VALUE_IS_ARRAY|InputOption::VALUE_OPTIONAL, 'Add one or multiple (array) tags')
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
        $jobName = $input->getArgument('job');

        $parameters = [];
        foreach ($input->getArgument('parameters') as $parameter) {
            list($name, $value) = explode(':', $parameter);
            $parameters[trim($name)] = trim($value);
        }

        $tags = [];
        foreach ($input->getOption('tags') as $tag) {
            $tags[] = trim($tag);
        }

        $task = new Task(
            new Profile($input->getArgument('profile')),
            new $jobName,
            $parameters, $tags
        );

        ServiceContainer::getInstance()
            ->queue
            ->add($task);

        $this->formatTaskBlock($task, $output);

        return 0;
    }
}
