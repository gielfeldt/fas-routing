<?php

namespace Fas\Routing;

use Fas\Autowire\Autowire;
use FastRoute\RouteCollector;
use Psr\Container\ContainerInterface;

class RouteGroup
{
    private ?ContainerInterface $container;
    private RouteCollector $routeCollector;
    private Middleware $middleware;
    private Autowire $autowire;

    public function __construct(?RouteGroup $parent = null, Autowire $autowire)
    {
        $this->autowire = $autowire;
        $this->container = $autowire->getContainer();
        $this->routeCollector = $parent ? $parent->routeCollector : new RouteCollector(new \FastRoute\RouteParser\Std(), new \FastRoute\DataGenerator\GroupCountBased());
        $this->middleware = new Middleware($autowire, $parent ? $parent->getMiddleware() : null);
    }

    public function map($httpMethod, $route, $handler): Route
    {
        $r = new Route($handler, $this);
        $this->routeCollector->addRoute($httpMethod, $route, $r);
        return $r;
    }

    public function group(): RouteGroup
    {
        return new RouteGroup($this, $this->autowire);
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

    public function getAutowire(): Autowire
    {
        return $this->autowire;
    }
}
