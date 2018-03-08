<?php

namespace JobQueue\Application\Console;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\ListCommand;

final class ManagerApplication extends Application
{
    public function __construct()
    {
        parent::__construct('manager', 1.0);

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
    }
}
