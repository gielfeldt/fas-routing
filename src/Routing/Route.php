<?php

namespace Fas\Routing;

use Fas\Autowire\Autowire;
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
    private Autowire $autowire;
    private Middleware $middleware;
    private array $args = [];

    public function __construct($callback, RouteGroup $routeGroup)
    {
        $this->callback = $callback;
        $this->autowire = $routeGroup->getAutowire();
        $this->routeGroup = $routeGroup;
        $this->middleware = new Middleware($this->autowire, $routeGroup->getMiddleware());
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
        $autowire = $this->autowire;
        if (!empty($this->middleware->getMiddlewares())) {
            $code = '
static function (\\Psr\\Http\\Message\\ServerRequestInterface $request, array $vars, ?\\Psr\\Container\\ContainerInterface $container) {
    $middlewares = ' . $exporter->export($this->middleware) . ';
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
