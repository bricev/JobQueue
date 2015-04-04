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

  * **Task** register all options and params to run Jobs, set profile, and
    track progress

  * **Queue** store and manage Tasks, provide Tasks for Workers

  * **Worker** takes Tasks from Queue to setup Jobs and execute them

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

Jobs must be setup through a `setup()` method that must at least provide
a name and may add some Job required parameters and settings, and pre
execute routines.

See example/Job to view a Job example.

Useful methods:

  * `$this->hasParameter($name)`            check if a parameter exists

  * `$this->getParameter($name, $default)`  retrieve a parameter by its name

  * `$this->setTaskProgress($float)`        to track Job progress while running


Add Tasks to Queue
------------------

Here is an example of how you create and add Tasks to Queue (you can also look
at the `task:import` command):

```php
<?php

require '/path/to/vendor/autoload.php';

use Libcast\JobQueue\Task\Task;
use Libcast\JobQueue\Queue\QueueFactory;
use Predis\Client;

$task = new Task(
    new Libcast\JobQueue\Test\DummyJob,
    'dummy',
    [
        'param1' => 'foo',
        'param2' => 'bar',
    ]
);

// setup a Redis client
$redis = new Client('tcp://localhost:6379');

// load Queue
$queue = QueueFactory::build($redis);

// add Tasks to the Queue
$queue->enqueue($task);
```


CLI
---

### Synopsis

    queue
      queue:show        List jobs from the queue
      queue:flush       Flush the queue
      queue:recover     Move all buggy Tasks to the `waiting` list
    task
      task:add          Add a Task
      task:delete       Delete a Task
      task:edit         Edit a Task
    worker
      worker:run        Run a worker

A typical command would look like:

    bin/jobqueue queue:show example/config.php
    bin/jobqueue worker:run profile example/config.php

