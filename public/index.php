<?php

$root = __DIR__ . '/..';

require_once realpath("$root/vendor/autoload.php");

use function Http\Response\send;
use JobQueue\Application\Http\ApiError;
use JobQueue\Application\Http\ApiRouting;
use Middlewares\ErrorHandler;
use Middlewares\FastRoute;
use Middlewares\JsonPayload;
use Middlewares\RequestHandler;
use Relay\Relay;
use Zend\Diactoros\ServerRequestFactory;

$response = (new Relay([

    (new ErrorHandler(new ApiError))->catchExceptions(true),

    new JsonPayload,

    new FastRoute(ApiRouting::createFromCache("$root/cache/routing.php")),

    new RequestHandler,

]))->handle(ServerRequestFactory::fromGlobals());

send($response);
