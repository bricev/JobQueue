<?php

namespace Libcast\JobQueue\TestJob;

use Libcast\JobQueue\Job\AbstractJob;
use Libcast\JobQueue\Job\JobInterface;
use Libcast\JobQueue\Exception\JobException;

/**
 * This is a Job class example.
 * Be sure to extend AbstractJob ans implement JobInterface.
 *
 * Allowed methods :
 *
 *   * initialize() set up the Job
 *     - MAY  call setOptions(array())
 *     - MAY  call setRequiredOptions(array())
 *     - MAY  call setParameters(array())
 *     - MAY  call setRequiredParameters(array())
 *
 *   * preRun() is executed before the Job
 *     - should run parent method at some point
 *
 *   * run() is the actual Job
 *     - should run parent method at some point
 *
 *   * postRun() is executed after the Job
 *     - should run parent method at some point
 */
class DummyFailingJob extends AbstractJob implements JobInterface
{
    protected function initialize()
    {
        $this->setOptions(array(
            'priority'  => 1,
            'profile'   => 'dummy-stuff',
        ));
    }

    protected function run()
    {
        throw new JobException('Dummy Job fails!');
    }
}
