<?php

namespace Fas\Routing;

use Fas\DI\Autowire;
use Fas\Exportable\ExportableInterface;
use Fas\Exportable\ExportableRaw;
use Fas\Exportable\Exporter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Route implements ExportableInterface, RequestHandlerInterface
{

    private $callback;
    private RouteGroup $routeGroup;
    private Middleware $middleware;
    private array $args = [];

    public function __construct($callback, RouteGroup $routeGroup)
    {
        $this->callback = $callback;
        $this->routeGroup = $routeGroup;
        $this->middleware = new Middleware($routeGroup->getContainer(), $routeGroup->getMiddleware());
    }

    public function middleware($middleware): Route
    {
        $this->middleware->add($middleware);
        return $this;
    }

    public function setArguments(array $args)
    {
        $this->args = $args;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $container = $this->routeGroup->getContainer();
        $autowire = new Autowire($container);
        $handler = new RouteRequestHandler($autowire, $this->callback, $this->args);
        return $this->middleware->process($request, $handler);
    }

    public function exportable(Exporter $exporter, $level = 0): string
    {
        $container = $this->routeGroup->getContainer();
        $autowire = new Autowire($container);
        $middlewares = [];
        foreach ($this->middleware->getMiddlewares() as $middleware) {
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

        if (!empty($middlewares)) {
            $code = '
static function (\\Psr\\Http\\Message\\ServerRequestInterface $request, array $vars, ?\\Psr\\Container\\ContainerInterface $container) {
    $middlewares = ' . $exporter->export($middlewares) . ';
    $callback = ' . $exporter->export(new ExportableRaw($autowire->compileCall($this->callback))) . ';
    $middleware = new \\' . CachedMiddleware::class . '($container, $middlewares);
    $handler = new \\' . CachedRequestHandler::class . '($callback, $vars, $container);
    return $middleware->process($request, $handler);
}';
        } else {
            $code = '
static function (\\Psr\\Http\\Message\\ServerRequestInterface $request, array $vars, ?\\Psr\\Container\\ContainerInterface $container) {
    $callback = ' . $exporter->export(new ExportableRaw($autowire->compileCall($this->callback))) . ';
    $handler = new \\' . CachedRequestHandler::class . '($callback, $vars, $container);
    return $handler->handle($request);
}';
        }
        return $code;
    }
}
