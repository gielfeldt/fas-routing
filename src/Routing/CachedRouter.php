<?php

namespace Fas\Routing;

use FastRoute\Dispatcher\GroupCountBased;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class CachedRouter implements RequestHandlerInterface
{
    private ?ContainerInterface $container;
    private array $routeGroupData;
    private CachedMiddleware $middlewares;

    public function __construct(?ContainerInterface $container = null, $data)
    {
        $this->container = $container;
        $this->routeGroupData = $data[0];
        $this->middlewares = new CachedMiddleware($container, $data[1]);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->middlewares->process($request, new CachedRouterHandler(new GroupCountBased($this->routeGroupData), $this->container));
    }
}
