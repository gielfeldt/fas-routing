<?php

namespace Fas\Routing;

use Fas\DI\Autowire;
use Fas\Exportable\ExportableInterface;
use Fas\Exportable\ExportableRaw;
use Fas\Exportable\Exporter;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;

class Route implements ExportableInterface
{

    private $callback;
    private RouteGroup $routeGroup;
    private array $middlewares = [];

    public function __construct($callback, RouteGroup $routeGroup)
    {
        $this->callback = $callback;
        $this->routeGroup = $routeGroup;
    }

    public function middleware($middleware): Route
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    public function getCallback()
    {
        return $this->callback;
    }

    public function getMiddlewares(): array
    {
        return $this->routeGroup ? array_merge($this->routeGroup->getMiddlewares(), $this->middlewares) : $this->middlewares;
    }

    public function __invoke(ServerRequestInterface $request, array $vars, ?ContainerInterface $container)
    {
        return (new RouteHandler($container))->handle($request, $this->getMiddlewares(), $this->callback, $vars);
    }

    public function exportable(Exporter $exporter, $level = 0): string
    {
        $container = $this->routeGroup->getContainer();
        $autowire = new Autowire($container);
        $middlewares = [];
        foreach ($this->getMiddlewares() as $middleware) {
            if (is_string($middleware) && $container && $container->has($middleware)) {
                $instance = $container->get($middleware);
                if ($instance instanceof MiddlewareInterface) {
                    $middleware = new ExportableRaw($autowire->compileCall([$middleware, 'process']));
                } elseif (is_callable($instance)) {
                    $middleware = new ExportableRaw($autowire->compileCall($middleware));
                }
            } elseif (is_string($middleware) && !$container && class_exists($middleware)) {
                $middleware = new ExportableRaw($autowire->compileCall([$middleware, 'process']));
            } else {
                $middleware = new ExportableRaw($autowire->compileCall($middleware));
            }
            $middlewares[] = $middleware;
        }

        $code = '
static function (\\Psr\\Http\\Message\\ServerRequestInterface $request, array $vars, ?\\Psr\\Container\\ContainerInterface $container) {
    $middlewares = ' . $exporter->export($middlewares) . ';
    $callback = ' . $exporter->export(new ExportableRaw($autowire->compileCall($this->callback))) . ';
    return (new \\' . CachedRouteHandler::class . '($container))->handle($request, $middlewares, $callback, $vars);
}';
        return $code;
    }
}
