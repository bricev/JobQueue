<?php

namespace JobQueue\Application\Http;

use JobQueue\Domain\Task\Profile;
use JobQueue\Domain\Task\Task;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response\JsonResponse;

final class AddTask extends BaseController
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

        $tags = isset($body['tags']) ? $body['tags'] : [];

        $task = new Task($profile, $job, $parameters, $tags);

        $this->queue->add($task);

        return new JsonResponse($task);
    }
}
