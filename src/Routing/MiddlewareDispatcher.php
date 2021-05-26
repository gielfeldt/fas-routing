<?php

namespace Fas\Routing;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MiddlewareDispatcher implements RequestHandlerInterface
{
    private RouteHandlerInterface $routeHandler;
    private array $middlewares;
    private $handler;
    private array $args;

    public function __construct(RouteHandlerInterface $routeHandler, array $middlewares, $handler, $args)
    {
        $this->handler = $handler;
        $this->routeHandler = $routeHandler;
        $this->middlewares = $middlewares;
        $this->args = $args;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $middleware = array_shift($this->middlewares);
        if (!$middleware) {
            return $this->routeHandler->callHandler($this->handler, ['request' => $request] + $this->args);
        }
        return $this->routeHandler->callMiddleware($middleware, $request, $this);
    }
}
