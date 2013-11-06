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
use Symfony\Component\Console\Input\InputInterface;
use Libcast\JobQueue\Queue\QueueInterface;
use Libcast\JobQueue\JobQueue;
use Libcast\JobQueue\Console\Command\AddDummyJobCommand;
use Libcast\JobQueue\Console\Command\DeleteJobCommand;
use Libcast\JobQueue\Console\Command\EditJobCommand;
use Libcast\JobQueue\Console\Command\FlushQueueCommand;
use Libcast\JobQueue\Console\Command\InfoUpstartCommand;
use Libcast\JobQueue\Console\Command\InstallUpstartCommand;
use Libcast\JobQueue\Console\Command\RebootQueueCommand;
use Libcast\JobQueue\Console\Command\StartUpstartCommand;
use Libcast\JobQueue\Console\Command\StatusUpstartCommand;
use Libcast\JobQueue\Console\Command\StopUpstartCommand;
use Libcast\JobQueue\Console\Command\ShowQueueCommand;
use Libcast\JobQueue\Console\Command\RunWorkerCommand;

class Application extends BaseApplication
{
    protected $parameters;

    /**
     * Constructor.
     *
     * @param \Libcast\JobQueue\Queue\QueueInterface  $queue
     * @param array                                   $parameters
     *
     * @api
     */
    function __construct()
    {
        parent::__construct('Libcast Job Queue CLI', JobQueue::VERSION);
        $this->add(new AddDummyJobCommand);
        $this->add(new DeleteJobCommand);
        $this->add(new EditJobCommand);
        $this->add(new FlushQueueCommand);
        $this->add(new InfoUpstartCommand);
        $this->add(new InstallUpstartCommand);
        $this->add(new RebootQueueCommand);
        $this->add(new StartUpstartCommand);
        $this->add(new StatusUpstartCommand);
        $this->add(new StopUpstartCommand);
        $this->add(new ShowQueueCommand);
        $this->add(new RunWorkerCommand);
    }
}
