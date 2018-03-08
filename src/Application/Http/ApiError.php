<?php

namespace JobQueue\Application\Http;

use Middlewares\HttpErrorException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response\JsonResponse;

final class ApiError implements RequestHandlerInterface
{
    /**
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $error = $request->getAttribute('error'); /** @var HttpErrorException $error */
        $error = $error->getPrevious() ?: $error;

        $body = [
            'message' => $error->getMessage(),
            'code' => 400,
        ];

        return (new JsonResponse($body))->withStatus(400);
    }
}
