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
     * @param bool   $disable
     * @return Dispatcher
     */
    public static function createFromCache(string $dir, bool $disable = false): Dispatcher
    {
        return cachedDispatcher(function (RouteCollector $r) {

             $r->get( '/tasks',             ListTasks::class );
            $r->post( '/tasks',             AddTask::class   );
             $r->get( '/task/{identifier}', ShowTask::class  );

        }, [
            'cacheFile' => $dir,
            'cacheDisabled' => $disable,
        ]);
    }
}
