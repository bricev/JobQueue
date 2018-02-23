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

        $body = [
            'message' => $error->getPrevious()->getMessage(),
            'code' => 400,
        ];

        return (new JsonResponse($body))->withStatus(400);
    }
}
