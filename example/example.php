<?php

require_once __DIR__ . '/../vendor/autoload.php';
include __DIR__ . '/Job/DummyJob.php';
include __DIR__ . '/Job/DummyFailingJob.php';

use Libcast\JobQueue\Task\Task;
use Predis\Client;
use Libcast\JobQueue\Queue\QueueFactory;
use Libcast\JobQueue\TestJob\DummyJob;
use Libcast\JobQueue\TestJob\DummyFailingJob;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logpath = realpath(__DIR__ . '/../log') . '/debug.log';
$logger = new Logger('Example');
$logger->pushHandler(new StreamHandler($logpath, Logger::DEBUG));

$redis = new Client('tcp://localhost:6379');
$factory = new QueueFactory;
$queue = $factory->load($redis, $logger);

$subTask_1 = new Task(new DummyFailingJob, array(), array(
    'track_num' => '1',
));

$subTask_2 = new Task(new DummyFailingJob, array(), array(
    'track_num' => '2',
));

$subTask_3 = new Task(new DummyFailingJob, array(), array(
    'track_num' => '3',
));

$taskWrapper = new Task(new DummyJob, array(), array(
    'destination' => '/tmp/dummytest',
));
$taskWrapper->addChild($subTask_1);
$taskWrapper->addChild($subTask_2);
$taskWrapper->addChild($subTask_3);

$queue->add($taskWrapper);

echo 'Done\n\n';