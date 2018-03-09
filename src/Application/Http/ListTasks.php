<?php

namespace JobQueue\Application\Http;

use JobQueue\Domain\Task\Profile;
use JobQueue\Domain\Task\Status;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response\JsonResponse;

final class ListTasks extends BaseController
{
    /**
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $request->getQueryParams();

        $tasks = $this
            ->queue
            ->search(
                isset($queryParams['profile']) ? new Profile($queryParams['profile']) : null,
                isset($queryParams['status']) ? new Status($queryParams['status']) : null,
                isset($queryParams['tags']) ? (array) $queryParams['tags'] : [],
                isset($queryParams['order']) ? $queryParams['order'] : 'status'
            );

        return new JsonResponse($tasks);
    }
}
