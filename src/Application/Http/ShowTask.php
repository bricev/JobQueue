<?php

namespace JobQueue\Application\Http;

use JobQueue\Infrastructure\ServiceContainer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response\JsonResponse;

class ShowTask implements RequestHandlerInterface
{
    /**
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $task = ServiceContainer::getInstance()
            ->queue
            ->find($request->getAttribute('identifier'));

        return new JsonResponse($task);
    }
}
