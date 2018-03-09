<?php

namespace JobQueue\Application\Console;

use JobQueue\Domain\Task\Queue;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\ListCommand;

final class ManagerApplication extends Application
{
    /**
     *
     * @param Queue $queue
     */
    public function __construct(Queue $queue)
    {
        parent::__construct('manager', 1.0);

        $manCommand = new ListCommand;
        $manCommand->setName('man');

        $this->add($manCommand);
        $this->add(new ListTasks($queue));
        $this->add(new AddTask($queue));
        $this->add(new ShowTask($queue));
        $this->add(new EditTask($queue));
        $this->add(new DeleteTask($queue));
        $this->add(new RestoreTasks($queue));
        $this->add(new FlushTasks($queue));
        $this->setDefaultCommand('man');
    }
}
