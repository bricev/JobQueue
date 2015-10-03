<?php

namespace Libcast\JobQueue;

trait LoggerTrait
{
    /**
     *
     * @var \Monolog\Logger|\Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     *
     * @param             $logger
     * @param Worker|null $worker
     * @param Task|null   $task
     * @throws \Exception
     */
    public function setLogger($logger, Worker $worker = null, Task $task = null)
    {
        if (is_null($logger)) {
            return;
        }

        if (!$logger instanceof \Psr\Log\LoggerInterface) {
            throw new \Exception('The logger is not supported');
        }

        $this->logger = $logger;

        if ($worker instanceof Worker) {
            $this->setLoggerWorker($worker);
        }

        if ($task instanceof Task) {
            $this->setLoggerTask($task);
        }
    }

    /**
     *
     * @param Worker $worker
     */
    public function setLoggerWorker(Worker $worker)
    {
        $this->logger->pushProcessor(function ($record) use ($worker) {
            $record['context']['tags']['worker_profile'] = $worker->getProfile();
            $record['context']['tags']['worker_name'] = $worker->getName();

            return $record;
        });
    }

    /**
     *
     * @param Task $task
     */
    public function setLoggerTask(Task $task)
    {
        $this->logger->pushProcessor(function ($record) use ($task) {
            $record['context']['tags']['task_id'] = $task->getId();
            $record['context']['tags']['task_name'] = $task->getName();

            return $record;
        });
    }

    /**
     *
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     *
     * @param        $message
     * @param array  $context
     * @param array  $tags
     * @param string $level
     */
    public function log($message, $context = [], $tags = [], $level = 'debug')
    {
        if (!$this->logger instanceof \Psr\Log\LoggerInterface) {
            return;
        }

        if (!is_array($context)) {
            $context = [$context];
        }

        if (!isset($context['tags'])) {
            $context['tags'] = [];
        }

        if ($tags) {
            $context['tags'] = array_merge($tags, $context['tags']);
        }

        if (empty($context['tags'])) {
            unset($context['tags']);
        }

        $this->logger->log($level, $message, $context);
    }

    /**
     *
     * @param       $message
     * @param array $context
     * @param array $tags
     */
    public function debug($message, $context = [], $tags = [])
    {
        $this->log($message, $context, $tags);
    }

    /**
     *
     * @param       $message
     * @param array $context
     * @param array $tags
     */
    public function info($message, $context = [], $tags = [])
    {
        $this->log($message, $context, $tags, 'info');
    }

    /**
     *
     * @param       $message
     * @param array $context
     * @param array $tags
     */
    public function warning($message, $context = [], $tags = [])
    {
        $this->log($message, $context, $tags, 'warning');
    }

    /**
     *
     * @param       $message
     * @param array $context
     * @param array $tags
     */
    public function error($message, $context = [], $tags = [])
    {
        $this->log($message, $context, $tags, 'error');
    }
}
