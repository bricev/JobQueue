<?php

namespace Libcast\Job\Job;

use Libcast\Job\Exception\JobException;
use Libcast\Job\Job\AbstractJob;
use Libcast\Job\Job\JobInterface;
use Libcast\Job\Queue\AbstractQueue;

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