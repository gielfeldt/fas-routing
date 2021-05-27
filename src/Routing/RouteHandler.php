<?php

namespace Fas\Routing;

use Fas\DI\Autowire;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RouteHandler implements RouteHandlerInterface
{
    private ContainerInterface $container;
    private ?Autowire $autowire;

    public function __construct(?ContainerInterface $container = null)
    {
        $this->autowire = new Autowire($container);
        $this->container = $this->autowire->getContainer();
    }

    public function handle(ServerRequestInterface $request, array $middlewares, $handler, array $args): ResponseInterface
    {
        $dispatcher = new MiddlewareDispatcher($this, $middlewares, $handler, $args);
        return $dispatcher->handle($request);
    }

    public function callMiddleware($middleware, ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (is_string($middleware) && $this->container->has($middleware)) {
            $middleware = $this->container->get($middleware);
        }
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware->process($request, $handler);
        }
        return $this->autowire->call($middleware, ['request' => $request, 'handler' => $handler]);
    }

    public function callHandler($handler, $args = [])
    {
        return $this->autowire->call($handler, $args);
    }
}
