<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Predis\Client;
use Libcast\JobQueue\JobQueue;
use Libcast\JobQueue\Queue\QueueFactory;

include __DIR__ . '/Job/DummyJob.php';
include __DIR__ . '/Job/DummyFailingJob.php';

// Set a logger (optional)
$logger = new Logger('JobQueue');
$logger->pushHandler(new StreamHandler(fopen('php://stdout', 'a'), Logger::DEBUG));

return new JobQueue([
    'queue'  => QueueFactory::build(new Client('tcp://localhost:6379')),
    'logger' => $logger,
]);
