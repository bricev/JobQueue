<?php

require_once __DIR__ . '/../vendor/autoload.php';
include __DIR__ . '/Job/DummyJob.php';
include __DIR__ . '/Job/DummyFailingJob.php';

use Libcast\JobQueue\Task;
use Predis\Client;
use Libcast\JobQueue\Queue\QueueFactory;
use Libcast\JobQueue\TestJob;

$redis = new Client('tcp://localhost:6379');
$queue = QueueFactory::build($redis);

$task = new Task(new TestJob\DummyJob, 'dummy', []);

$queue->enqueue($task);

echo 'Done\n\n';
