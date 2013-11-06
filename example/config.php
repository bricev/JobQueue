<?php

use Monolog\Logger;
use Predis\Client;
use Libcast\JobQueue\JobQueue;
use Libcast\JobQueue\Queue\RedisQueue;

$logger = new Logger("JobQueue");

// You may load more job classes here, when they are not handled by
// any autoloader.
include __DIR__ . '/Job/DummyJob.php';
include __DIR__ . '/Job/DummyLongJob.php';

return new JobQueue(array(
    "workers" => array(
        "w1" => array("notsodummy"),
        "w2" => array("dummy-stuff"),
    ),
    "queue"  => new RedisQueue(new Client("tcp://localhost:6379"), $logger),
    "logger" => $logger,
));
