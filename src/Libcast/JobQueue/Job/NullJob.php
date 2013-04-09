<?php

namespace Libcast\JobQueue\Job;

use Libcast\JobQueue\Exception\JobException;
use Libcast\JobQueue\Job\AbstractJob;
use Libcast\JobQueue\Job\JobInterface;
use Libcast\JobQueue\Queue\AbstractQueue;

class NullJob extends AbstractJob implements JobInterface
{
  protected function initialize()
  {
    $this->setName('Null Job');

    $this->setOptions(array(
        'priority'  => 0,
        'profile'   => 0,
    ));
  }

  protected function run()
  {
    throw new JobException('This Job is not meant to be executed.');
  }
}