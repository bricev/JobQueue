PHP JobQueue component
======================

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/bricev/JobQueue/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/bricev/JobQueue/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/bricev/JobQueue/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/bricev/JobQueue/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/bricev/JobQueue/badges/build.png?b=master)](https://scrutinizer-ci.com/g/bricev/JobQueue/build-status/master)

Simple Job/Queue PHP component to help applications distribute Tasks through
multiple workers.

## Install

This package is installable and auto-loadable via Composer:
```shell
$ composer require bricev/jobqueue
```

The application proposes a webservice and two CLIs.
Read bellow for more documentation.

## Configuration

- PHP 7.1 must be installed
- Redis must be installed (for queue data persistence)
- the `JOBQUEUE_ENV` environment variable may be set as `dev`, `prod` (or any string, default if not set: `dev`)
- the `JOBQUEUE_REDIS_DSN` environment variable must define the Redis DSN (eg. 'tcp://127.0.0.1:6379')

## Usage

### Defining a job

A `job` holds the code that will be executed for tasks by a worker.

Each job must be defined in a PHP class that implements the `ExecutableJob` interface:
```php
<?php

use JobQueue\Domain\Job\ExecutableJob;
use JobQueue\Domain\Task\Task;
use Psr\Log\LoggerAwareTrait;

final class DummyJob implements ExecutableJob
{
    use LoggerAwareTrait;

    /**
     *
     * @param Task $task
     */
    function setUp(Task $task)
    {
        // This is called before the `perform()` method
        /** @todo prepare the job execution */
    }

    /**
     *
     * @param Task $task
     */
    function perform(Task $task)
    {
        /** @todo do the job! */
    }

    /**
     *
     * @param Task $task
     */
    function tearDown(Task $task)
    {
        // This is called after the `perform()` method
        /** @todo clean up after the job execution */
    }
}

```

Note: `ExecutableJob` interface extends the `LoggerAwareInterface` that can be used to set a logger.
This [documentation](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md) provides more information about PSR-3 Log interfaces.

Tip: Package `psr/log` ([repo](https://github.com/php-fig/log)) can be added to a composer project by running this command:
```
composer require psr/log
```

The `\Psr\Log\LoggerAwareTrait` can be used to easily add a logger setter and be compliant with the `LoggerAwareInterface`.


### Defining a task

Creating a `task` requires the following elements:
- a `profile` - it can be anything, it regroups tasks into queue partitions, so that the JobQueue `worker` app can consume tasks from one (only) profile
- a `job` - which holds the code that has to be executed for the task
- optional `parameters` that can be used by the job during its execution 

To define a task in PHP:
```php
<?php

use JobQueue\Domain\Task\Profile;
use JobQueue\Domain\Task\Task;
use JobQueue\Tests\Domain\Job\DummyJob;

$task = new Task(
    new Profile('foobar'),
    new DummyJob,
    [
        'foo' => 'bar',
        // [...]
    ]
);

```

### Adding tasks to the queue

First, the queue has to be instantiated.

This can be done manually:
```php
<?php

use JobQueue\Infrastructure\RedisQueue;
use Predis\Client;

$predis = new Client('tcp://localhost:6379');
$queue = new RedisQueue($predis);

```

Or by using the ServiceContainer (this requires the proper configuration, see `Configuration` section above):
```php
<?php

use JobQueue\Infrastructure\ServiceContainer;

$queue = ServiceContainer::getInstance()->queue;

```

Then, tasks can be enqueued easily:
```php
<?php

/** @var \JobQueue\Domain\Task\Queue $queue */
/** @var \JobQueue\Domain\Task\Task $task */

$queue->add($task);

```

The task's job will be executed as soon as a worker starts consuming the task's profile.
This component embeds a PHP executable worker. 
See the `CLI` section to get more details about its usage.


### Worker events

The worker emits some events that can be listened to:

| Event name | Description | Event attributes |
| --- | --- | --- |
| `worker.start` | Fired on worker launch | $event->getWorker() |
| `worker.finished` | Fired once the worker has finished running | $event->getWorker() |
| `task.fetched` | Fired each time a task is fetched from queue by the worker | $event->getTask() |
| `task.executed` | Fired when a task's job execution has been successful done | $event->getTask() |
| `task.failed` | Fired when a task's job execution fails | $event->getTask() |

To intercept an event, you can use the `EventDispatcher` from the service container:
```php
<?php

use JobQueue\Infrastructure\ServiceContainer;

$dispatcher = ServiceContainer::getInstance()->dispatcher;
$dispatcher->addListener('task.failed', function ($event) {
    /** @var \JobQueue\Domain\Task\TaskHasFailed $event */
    $task = $event->getTask();
    // Do something...
});

```

## CLI

Those features require the proper configuration, see `Configuration` section above.

The `manager` app can be used to perform CRUD operations on tasks.

Usage:
```shell
$ bin/manager list               # lists all commands
$ bin/manager {command} --help   # display the command help
```

The `worker` app can be used to consume enqueued tasks.

Usage:
```shell
$ bin/worker --help
```

The `worker` app can be used as an OS service (eg. upstart, systemd... on unix) to run on servers.


## Webservice

### Configuration

A web server should be configured to serve `public/index.php` as a router script.
This feature requires the proper configuration, see `Configuration` section above.


### API

__List all tasks:__
```
GET /tasks
profile: string (a profile name that filters tasks)
status: waiting|running|finished|failed (a status that filters tasks)
order: date|profile|status (sort order, default: status)
```

Returns an array of tasks:
```
HTTP/1.1 200 Ok
Content-Type: application/json

[
    {
        "identifier": "47a5b21d-0a02-4e6e-b8c9-51dc1534cb68",
        "status": "waiting",
        "profile": "foobar",
        "job": "JobQueue\\Tests\\Domain\\Job\\DummyJob",
        "date": "Fri, 23 Feb 2018 13:45:22 +0000",
        "parameters": {
            "name_1": "value_1",
            "name_2": "value_2"
        }
    }
]
```

Errors:
- `400 Bad Request` if one of the parameters is wrong:
  - `Status "foo" does not exists` is status is not equal to `waiting|running|finished|failed` 
  - `Profile name only allows lowercase alphanumerical, dash and underscore characters` is profile is malformed 
  - `Impossible to order by "foobar"` is order is not equal to `date|profile|status`
- `500 Internal Server Error` in case of a technical error

__Get a task information:__
```
GET /task/{identifier}
```

Returns the task definition:
```
HTTP/1.1 200 Ok
Content-Type: application/json

{
    "identifier": "47a5b21d-0a02-4e6e-b8c9-51dc1534cb68",
    "status": "waiting",
    "profile": "foobar",
    "job": "JobQueue\\Tests\\Domain\\Job\\DummyJob",
    "date": "Fri, 23 Feb 2018 13:45:22 +0000",
    "parameters": {
        "name_1": "value_1",
        "name_2": "value_2"
    }
}
```

Errors:
- `404 Not Found` if no task correspond to the identifier
- `500 Internal Server Error` in case of a technical error

__Create a new task:__
```
POST /tasks

{
    "profile": "foobar",
    "job": "JobQueue\\Tests\\Domain\\Job\\DummyJob",
    "parameters": {
        "name_1": "value_1",
        "name_2": "value_2"
    }
}
```

Returns the task definition:
```
HTTP/1.1 201 Created
Content-Type: application/json

{
    "identifier": "47a5b21d-0a02-4e6e-b8c9-51dc1534cb68",
    "status": "waiting",
    "profile": "foobar",
    "job": "JobQueue\\Tests\\Domain\\Job\\DummyJob",
    "date": "Fri, 23 Feb 2018 13:45:22 +0000",
    "parameters": {
        "name_1": "value_1",
        "name_2": "value_2"
    }
}
```

Errors:
- `400 Bad Request` if the JSON body is malformed:
  - `Missing job` if the JSON object miss a `job` value.
  - `Missing profile` if the JSON object miss a `profile` value.
  - `Profile name only allows lowercase alphanumerical, dash and underscore characters` is profile is malformed 
  - `Malformed parameters` is the `parameters` value from the JSON object is not a key/value array
- `500 Internal Server Error` in case of a technical error


## Tests

First, a Redis server must run locally (127.0.0.1 on 6379 port).

Then, to run tests, use the following command:
 ```shell
$ php vendor/bin/phpunit
 ```
 
The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
