<?php

namespace Libcast\JobQueue\Worker;

interface WorkerInterface
{
  /**
   * Launch to Worker that listen to Queue and submit Tasks to Jobs so that they
   * may run them.
   * 
   * Use with caution.
   * Infinite loop.
   * This method should be used in a daemonized application.
   * 
   * @throws \Libcast\JobQueue\Exception\WorkerException
   */
  public function run();
}