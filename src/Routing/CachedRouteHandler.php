<?php

namespace Fas\Routing;

use Fas\DI\Autowire;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CachedRouteHandler implements RouteHandlerInterface
{
    private ?ContainerInterface $container;
    private ?Autowire $autowire;

    public function __construct(?ContainerInterface $container = null)
    {
        $this->container = $container;
        $this->autowire = new Autowire($container);
    }

    public function handle(ServerRequestInterface $request, array $middlewares, $handler, array $args): ResponseInterface
    {
        $dispatcher = new MiddlewareDispatcher($this, $middlewares, $handler, $args);
        return $dispatcher->handle($request);
    }

    public function callMiddleware($middleware, ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $middleware(['request' => $request, 'handler' => $handler], $this->container);
    }

    public function callHandler($handler, $args = [])
    {
        return $handler($args, $this->container);
    }
}
