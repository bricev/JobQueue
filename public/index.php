<?php

$root = __DIR__ . '/..';

require_once realpath("$root/vendor/autoload.php");

use function Http\Response\send;
use JobQueue\Application\Http\ApiError;
use JobQueue\Application\Http\ApiRouting;
use JobQueue\Infrastructure\ServiceContainer;
use Middlewares\ErrorHandler;
use Middlewares\FastRoute;
use Middlewares\JsonPayload;
use Middlewares\RequestHandler;
use Middlewares\Utils\RequestHandlerContainer;
use Relay\Relay;
use Zend\Diactoros\ServerRequestFactory;

$queue = ServiceContainer::getInstance()->queue;

$response = (new Relay([

    (new ErrorHandler(new ApiError))->catchExceptions(true),

    new JsonPayload,

    new FastRoute(ApiRouting::create()),

    new RequestHandler(new RequestHandlerContainer([$queue])),

]))->handle(ServerRequestFactory::fromGlobals());

send($response);
