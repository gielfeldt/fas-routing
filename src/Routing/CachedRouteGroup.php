<?php

namespace Fas\Routing;

use BadMethodCallException;

class CachedRouteGroup extends RouteGroup
{
    public function __construct(array $dispatchData)
    {
        $this->dispatchData = $dispatchData;
    }

    public function getData()
    {
        return $this->dispatchData;
    }

    public function map($httpMethod, $route, $handler): Route
    {
        throw new BadMethodCallException("Cannot modify a cached route group");
    }

    public function group(callable $callback = null): RouteGroup
    {
        throw new BadMethodCallException("Cannot modify a cached route group");
    }

    public function middleware($middleware): RouteGroup
    {
        throw new BadMethodCallException("Cannot modify a cached route group");
    }
}
