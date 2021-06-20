<?php

namespace Fas\Routing;

use FastRoute\RouteCollector;
use Psr\Container\ContainerInterface;

class RouteGroup
{
    private ?ContainerInterface $container;
    private RouteCollector $routeCollector;
    private Middleware $middleware;

    public function __construct(?RouteGroup $parent = null, ?ContainerInterface $container)
    {
        $this->container = $container;
        $this->routeCollector = $parent ? $parent->routeCollector : new RouteCollector(new \FastRoute\RouteParser\Std(), new \FastRoute\DataGenerator\GroupCountBased());
        $this->middleware = new Middleware($container, $parent ? $parent->getMiddleware() : null);
    }

    public function map($httpMethod, $route, $handler): Route
    {
        $r = new Route($handler, $this);
        $this->routeCollector->addRoute($httpMethod, $route, $r);
        return $r;
    }

    public function group(): RouteGroup
    {
        return new RouteGroup($this, $this->container);
    }

    public function middleware($middleware): RouteGroup
    {
        $this->middleware->add($middleware);
        return $this;
    }

    public function getContainer(): ?ContainerInterface
    {
        return $this->container;
    }

    public function getMiddleware(): Middleware
    {
        return $this->middleware;
    }

    public function getData()
    {
        return $this->routeCollector->getData();
    }
}
