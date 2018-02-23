<?php

namespace JobQueue\Application\Http;

use JobQueue\Domain\Task\Profile;
use JobQueue\Domain\Task\Task;
use JobQueue\Infrastructure\ServiceContainer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response\JsonResponse;

class AddTask implements RequestHandlerInterface
{
    /**
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();

        if (!isset($body['profile']) or !$profile = new Profile($body['profile'])) {
            throw new \RuntimeException('Missing profile');
        }

        if (!isset($body['job']) or !$job = new $body['job']) {
            throw new \RuntimeException('Missing job');
        }

        $parameters = isset($body['parameters']) ? $body['parameters'] : [];
        if (!is_array($parameters)) {
            throw new \RuntimeException('Malformed parameters');
        }

        $task = new Task($profile, $job, $parameters);

        ServiceContainer::getInstance()
            ->queue
            ->add($task);

        return new JsonResponse($task);
    }
}
