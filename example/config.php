<?php

date_default_timezone_set('Europe/Paris');

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Predis\Client;
use Libcast\JobQueue\JobQueue;
use Libcast\JobQueue\Queue\RedisQueue;

$logger = new Logger('JobQueue');
$logger->pushHandler(new StreamHandler(realpath(__DIR__ . '/../log') . '/debug.log', Logger::DEBUG));

// You may load more job classes here, when they are not handled by
// any autoloader.
include __DIR__ . '/Job/DummyJob.php';
include __DIR__ . '/Job/DummyFailingJob.php';

return new JobQueue(array(
    'workers' => array(
        'dummy1' => array('dummy-stuff'),
        'dummy2' => array('dummy-stuff'),
    ),
    'queue'  => new RedisQueue(new Client('tcp://localhost:6379'), $logger),
    'logger' => $logger,
));
