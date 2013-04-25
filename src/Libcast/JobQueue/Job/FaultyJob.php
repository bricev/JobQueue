<?php

namespace Libcast\JobQueue\Job;

use Libcast\JobQueue\Exception\JobException;
use Libcast\JobQueue\Job\AbstractJob;
use Libcast\JobQueue\Job\JobInterface;

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
class FaultyJob extends AbstractJob implements JobInterface
{
  protected function initialize()
  {
    $this->setOptions(array(
        'priority'  => 1,
        'profile'   => 'dummy-stuff',
    ));

    $this->setRequiredParameters(array(
        'destination',
        'dummytext',
    ));
  }

  protected function run()
  {
    $max = rand(2, 5);
    for ($i = 1; $i <= $max; $i++)
    {
      $time = time();
      exec("echo '{$this->getParameter('dummytext')}:$time' >> {$this->getParameter('destination')}");

      $this->setTaskProgress($i/$max);

      sleep(1);
    }

    if (rand(0, 100000) > 30000) 
    {
      throw new JobException('FaultyJob random error!');
    }

    return parent::run();
  }
}