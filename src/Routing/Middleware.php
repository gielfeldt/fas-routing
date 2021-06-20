<?php

namespace Fas\Routing;

use Fas\DI\Autowire;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Middleware implements MiddlewareInterface, RequestHandlerInterface
{
    private array $middlewares = [];
    private Autowire $autowire;
    private ContainerInterface $container;
    private $handler;
    private ?array $stack = null;
    private ?Middleware $parent;

    public function __construct(?ContainerInterface $container = null, ?Middleware $parent = null)
    {
        $this->autowire = new Autowire($container);
        $this->container = $this->autowire->getContainer();
        $this->parent = $parent;
    }

    public function add(...$middlewares)
    {
        array_push($this->middlewares, ...$middlewares);
    }

    public function getMiddlewares()
    {
        return $this->parent ? array_merge($this->parent->getMiddlewares(), $this->middlewares) : $this->middlewares;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->stack = $this->getMiddlewares();
        $this->handler = $handler;

        return $this->handle($request);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $middleware = array_shift($this->stack);
        if (empty($middleware)) {
            return $this->handler->handle($request);
        }

        if (is_string($middleware) && $this->container->has($middleware)) {
            $middleware = $this->container->get($middleware);
        }
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware->process($request, $this);
        }
        return $this->autowire->call($middleware, ['request' => $request, 'handler' => $this]);
    }
}
