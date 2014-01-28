PHP JobQueue component
======================

Simple Job/Queue PHP composent to help applications distribute Tasks through
multiple workers.

Tasks may have children (sub tasks that are added to Queue when their parents
have been successfuly executed).

Queue enables to follow Tasks progress. A Task reach 100% progress when all its
children have been finished too.

Currently only Redis has been integrated to store Queue data.

Vocabulary:

  * **Job** define the work

  * **Task** register all options and params to run Jobs, set priority and
    profile, track progress

  * **Queue** store and manage Tasks, provide Tasks for Workers

  * **Worker** takes Tasks from Queue to setup Jobs and execute them, respect
    priority order

  * **priority** from 1 (lesser) to +inf, sets the order in which Tasks are
    submitted to Workers

  * **profile** affected to Tasks, Workers are then set up to only take Tasks
    having certain profiles

  * **status** a Task may have one of the following profiles:
    - `pending`  not in Queue yet
    - `waiting`  in Queue
    - `running`  currently setting up a Job that is executed
    - `success`  successfuly executed, may still have children not yet treated
    - `failed`   an error prevented the Job from being executed
    - `finished` out of Queue, successfuly treated, not more waiting children

Install
-------

Use composer to install the composent dependancies :

	cd /path/to/composent
	php composer.phar install


Write a Job
-----------

Jobs are simple class that must extend the `AbstractJob` parent class.

Jobs must be setup through an `inithialize()` method that must at least provide
a name and may add some Job default options and parameters, or set some required
options and parameters.

See example/Job to view a Job example.

Usefull methods:

  * `$this->getOption($name)`        retrieve an option by its name

  * `$this->getParameter($name)`     retrieve a parameter by its name

  * `$this->setTaskProgress($float)` to track Job progress while running


Add Tasks to Queue
------------------

Here is an example of how you create and add Tasks to Queue (you can also look
at the `job:add-dummy` command):

```php
<?php

require '/path/to/vendor/autoload.php';

use Libcast\JobQueue\Task\Task;
use Libcast\JobQueue\Queue\QueueFactory;
use Predis\Client;

$task = new Task(
        new DummyJob,
        array(),
        array(
            'input'       => 'aaaaaa',
            'destination' => '/tmp/file',
        )
);

// setup a Redis client
$redis = new Client('tcp://localhost:6379');

// load Queue
$queue = QueueFactory::load($redis);

// add all Tasks to Queue
$queue->add($task);
```


CLI
---

### Synopsis

    queue
      queue:flush       Flush the queue
      queue:reboot      Reboot the queue
      queue:show        List jobs from the queue
    task
      task:add          Add a Task
      task:delete       Delete a Task
      task:edit         Edit a Task
    upstart
      upstart:info      Give info and status for workers
      upstart:install   Install workers' conf in /etc/init/
      upstart:start     Proxy task to start workers via upstart
      upstart:status    Proxy task to get workers' status via upstart
      upstart:stop      Proxy task to stop workers via upstart
    worker
      worker:run        Run a worker

A typical command would look like:

    bin/jobqueue queue:show example/config.php
    bin/jobqueue worker:run w1 example/config.php
    bin/jobqueue upstart:control status example/config.php


You can also deploy a worker as an upstart service (in `/etc/init/`).

    # Ubuntu
    sudo bin/jobqueue upstart:install example/config.php
