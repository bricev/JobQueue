<?php

/*
 * This file is part of Libcast JobQueue component.
 *
 * (c) Brice Vercoustre <brcvrcstr@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Libcast\JobQueue\Console;

use Symfony\Component\Console\Application as BaseApplication;
use Libcast\JobQueue\JobQueue;
use Libcast\JobQueue\Console\Command;

class Application extends BaseApplication
{
    /**
     *
     * @param \Libcast\JobQueue\Queue\QueueInterface  $queue
     * @param array                                   $parameters
     *
     * @api
     */
    public function __construct()
    {
        parent::__construct('Libcast Job Queue CLI', JobQueue::VERSION);

        // Task commands
        $this->add(new Command\AddTaskCommand);
        $this->add(new Command\ImportTaskCommand);
        $this->add(new Command\EditTaskCommand);
        $this->add(new Command\DeleteTaskCommand);

        // Queue commands
        $this->add(new Command\ShowQueueCommand);
        $this->add(new Command\FlushQueueCommand);
        $this->add(new Command\RecoverQueueCommand);

        // Worker commands
        $this->add(new Command\RunWorkerCommand);
    }
}
