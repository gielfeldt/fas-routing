<?php

namespace Fas\Routing;

use Fas\Autowire\Autowire;
use Fas\Exportable\ExportableInterface;
use Fas\Exportable\ExportableRaw;
use Fas\Exportable\Exporter;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Middleware implements MiddlewareInterface, RequestHandlerInterface, ExportableInterface
{
    private array $middlewares = [];
    private Autowire $autowire;
    private ContainerInterface $container;
    private $handler;
    private ?array $stack = null;
    private ?Middleware $parent;

    public function __construct(Autowire $autowire, ?Middleware $parent = null)
    {
        $this->autowire = $autowire;
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


    public function exportable(Exporter $exporter, $level = 0): string
    {
        $autowire = $this->autowire;
        $middlewares = [];
        foreach ($this->getMiddlewares() as $middleware) {
            if (is_string($middleware) && $autowire->getContainer()->has($middleware)) {
                $instance = $autowire->getContainer()->get($middleware);
                if ($instance instanceof MiddlewareInterface) {
                    $middleware = new ExportableRaw($autowire->compileCall([$middleware, 'process']));
                } elseif (is_callable($instance)) {
                    $middleware = new ExportableRaw($autowire->compileCall($middleware));
                }
            } else {
                $middleware = new ExportableRaw($autowire->compileCall($middleware));
            }
            $middlewares[] = $middleware;
        }

        return $exporter->export($middlewares);
    }
}
