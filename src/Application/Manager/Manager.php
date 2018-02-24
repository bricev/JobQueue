<?php

namespace JobQueue\Application\Manager;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\ListCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class Manager extends Application
{
    public function __construct()
    {
        parent::__construct('manager', 1.0);
    }

    /**
     *
     * @param InputInterface|null $input
     * @param OutputInterface|null $output
     * @return int
     */
    public function run(InputInterface $input = null, OutputInterface $output = null)
    {
        $manCommand = new ListCommand;
        $manCommand->setName('man');

        $this->add($manCommand);
        $this->add(new ListTasks);
        $this->add(new AddTask);
        $this->add(new ShowTask);
        $this->add(new EditTask);
        $this->add(new DeleteTask);
        $this->add(new RestoreTasks);
        $this->add(new FlushTasks);
        $this->setDefaultCommand('man');

        return parent::run($input, $output);
    }
}
