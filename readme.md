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

The following file may illustrate how to create a Job:

```php
<?php

namespace Libcast\Job\Job;

use Libcast\Job\Job\AbstractJob;
use Libcast\Job\Job\JobInterface;

class DummyJob extends AbstractJob implements JobInterface
{
  protected function initialize()
  {	
    $this->setOptions(array(
        'priority'  => 0,
        'profile'   => 'dummy-stuff',
    ));
  }

  protected function run()
  {
    // do stuff...

    return true;
  }
}
```

Usefull methods:

  * `$this->getOption($name)`        retrieve an option by its name

  * `$this->getParameter($name)`     retrieve a parameter by its name

  * `$this->setTaskProgress($float)` to track Job progress while running


Write a Worker
--------------

See /example/worker to understand how to write your own Workers.

Ubuntu/Debian users may use /example/worker.conf to set up an `upstart` script 
in order to daemonize their Workers.


Add Tasks to Queue
------------------

Here is an example of how you create and add Tasks to Queue:

```php
<?php

require '/path/to/vendor/autoload.php';

use Libcast\JobQueue\Task\Task;
use Libcast\JobQueue\Job\DummyJob;
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
/* @var $queue \Libcast\JobQueue\Queue\RedisQueue */

// add all Tasks to Queue
$queue->add($task);
```


CLI
---

This component comes with a cli application.
See /example/cli for more details.