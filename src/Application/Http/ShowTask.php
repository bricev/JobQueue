<?php

namespace JobQueue\Application\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response\JsonResponse;

final class ShowTask extends BaseController
{
    /**
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $task = $this
            ->queue
            ->find($request->getAttribute('identifier'));

        return new JsonResponse($task);
    }
}
