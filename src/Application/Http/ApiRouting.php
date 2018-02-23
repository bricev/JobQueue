<?php

namespace JobQueue\Application\Http;

use function FastRoute\cachedDispatcher;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;

final class ApiRouting
{
    /**
     *
     * @param string $dir
     * @return Dispatcher
     */
    public static function createFromCache(string $dir): Dispatcher
    {
        return cachedDispatcher(function (RouteCollector $r) {
            $r->get('/tasks', new ListTasks);
            $r->post('/tasks', new AddTask);
            $r->get('/task/{identifier}', new ShowTask);

        }, [
            'cacheFile' => $dir,
            'cacheDisabled' => true,
        ]);
    }
}
