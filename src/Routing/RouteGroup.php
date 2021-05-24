<?php

namespace Fas\Routing;

use FastRoute\RouteCollector;

class RouteGroup
{
    private RouteCollector $routeCollector;
    private ?RouteGroup $parent = null;
    private array $middlewares = [];

    public function __construct(?RouteGroup $parent = null)
    {
        $this->routeCollector = $parent ? $parent->routeCollector : new \FastRoute\RouteCollector(new \FastRoute\RouteParser\Std, new \FastRoute\DataGenerator\GroupCountBased);
        $this->parent = $parent;
    }

    public function map($httpMethod, $route, $handler): Route
    {
        $r = new Route($handler, $this);
        $this->routeCollector->addRoute($httpMethod, $route, $r);
        return $r;
    }

    public function group(callable $callback = null): RouteGroup
    {
        $r = new RouteGroup($this);
        if ($callback) {
            $callback($r);
        }
        return $r;
    }

    public function middleware($middleware): RouteGroup
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    public function getMiddlewares(): array
    {
        return $this->parent ? array_merge($this->parent->getMiddlewares(), $this->middlewares) : $this->middlewares;
    }

    public function getData()
    {
        return $this->routeCollector->getData();
    }

}
