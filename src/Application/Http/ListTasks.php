<?php

namespace JobQueue\Application\Http;

use JobQueue\Domain\Task\Profile;
use JobQueue\Domain\Task\Status;
use JobQueue\Infrastructure\ServiceContainer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response\JsonResponse;

class ListTasks implements RequestHandlerInterface
{
    /**
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $request->getQueryParams();

        $tasks = ServiceContainer::getInstance()
            ->queue
            ->dump(
                isset($queryParams['profile']) ? new Profile($queryParams['profile']) : null,
                isset($queryParams['status']) ? new Status($queryParams['status']) : null,
                isset($queryParams['order']) ? $queryParams['order'] : 'status'
            );

        return new JsonResponse($tasks);
    }
}
