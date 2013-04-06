PHP JobQueue composent
======================

Simple Job/Queue PHP composent to help applications distribute Tasks through multiple workers.

Tasks may have children (sub tasks that are added to Queue when their parents have been successfuly
executed).

Queue enables to follow Tasks progress. A Task reach 100% progress when all its children have
been finished too.

Currently only Redis has been integrated to store Queue data.

Vocabulary:

  * Job : define the work
  * Task : register all options and params to run Jobs, set priority and profile, track progress
  * Queue : store and manage Tasks, provide Tasks for Workers
  * Worker : takes Tasks from Queue to setup Jobs and execute them, respect priority order
  * priority : from 1 (lesser) to +inf, sets the order in which Tasks are submitted to Workers
  * profile : affected to Tasks, Workers are then set up to only take Tasks having certain profiles
  * status : a Task may have one of the following profiles: 
    - `pending` (not in Queue yet)
    - `waiting` (in Queue)
    - `running` (currently setting up a Job that is executed)
    - `success` (successfuly executed, may still have some children not yet treated)
    - `failed` (an error prevented the Job from being executed)
    - `finished` (out of Queue, successfuly treated, not more waiting children)

Install
-------

Use composer to install the composent dependancies :

	cd /path/to/composent
	php composer.phar install


Write a Job
-----------

Jobs are simple class that must extend the `AbstractJob` parent class.

Jobs must be setup through an `inithialize()` method that must at least provide a name and may 
add some Job default options and parameters, or set some required options and parameters.

The following file may illustrate how to create a Job (see /example/DummyJob.php):

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


Write a Worker
--------------

See /example/worker to understand how to write your own Workers.

Ubuntu or Debian users mays use /example/worker.conf to set up an `upstart` script 
to help daemonize their Workers.


Add Tasks to Queue
------------------

Here is an example of how you create and add Tasks to Queue (see /example/insert_tasks.php):

	<?php
	
	require __DIR__.'/vendor/autoload.php';
	
	use Libcast\Job\Task\Task;
	use Libcast\Job\Job\DummyJob;
	use Libcast\Job\Job\FaultyJob;
	use Libcast\Job\Queue\QueueFactory;
	
	$task = new Task(
	        new DummyJob, // DummyJob writes `dummytext` param in `destination` param 
	        array(
	            'profile' => 'dummy-stuff', 
	        ),
	        array(
	            'dummytext' => 'xxx',
	            'destination' => '/tmp/notsodummy_profiled',
	        )
	);
	
	$queueFactory = new QueueFactory(
	        'redis',
	        array('host' => 'localhost', 'port' => 6379)
	);
	
	$queue = $queueFactory->getQueue(); /* @var $queue \Libcast\Job\Queue\RedisQueue */
	$queue->add($basic);


CLI
---

This component comes with a cli:

	./cli list:task --help
	./cli edit:task --help
