<?php

namespace Fas\Routing;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CachedMiddleware implements MiddlewareInterface, RequestHandlerInterface
{
    private array $middlewares = [];
    private ?ContainerInterface $container;
    private $handler;
    private ?array $stack = null;

    public function __construct(?ContainerInterface $container = null, array $middlewares)
    {
        $this->container = $container;
        $this->middlewares = $middlewares;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->stack = $this->middlewares;
        $this->handler = $handler;

        return $this->handle($request);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $middleware = array_shift($this->stack);
        if (empty($middleware)) {
            return $this->handler->handle($request);
        }
        return $middleware(['request' => $request, 'handler' => $this], $this->container);
    }
}
