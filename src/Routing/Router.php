<?php

namespace Fas\Routing;

use Fas\Autowire\Autowire;
use Fas\Autowire\Container;
use Fas\Exportable\ExportableInterface;
use Fas\Exportable\ExportableRaw;
use Fas\Exportable\Exporter;
use FastRoute\Dispatcher\GroupCountBased;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Router implements ExportableInterface, RequestHandlerInterface
{
    private ?ContainerInterface $container;
    private RouteGroup $routeGroup;
    private Autowire $autowire;
    private Middleware $middleware;

    public function __construct(?ContainerInterface $container = null, ?RouteGroup $routeGroup = null)
    {
        $this->autowire = new Autowire($container);
        $this->container = $this->autowire->getContainer();
        $this->routeGroup = $routeGroup ?? new RouteGroup(null, $this->autowire);
        $this->middleware = new Middleware($this->autowire);
    }

    public static function load($filename, ?ContainerInterface $container = null): ?CachedRouter
    {
        $data = @include $filename;
        if (!is_array($data)) {
            return null;
        }
        $router = new CachedRouter($container, $data);
        return $router;
    }

    public function save($filename): void
    {
        $tempfile = tempnam(dirname($filename), 'routegroup');
        $exporter = new Exporter();
        $exported = $exporter->export($this);
        file_put_contents($tempfile, '<?php return ' . $exported . ';');
        rename($tempfile, $filename);
    }

    public function map($httpMethod, $route, $handler): Route
    {
        return $this->routeGroup->map($httpMethod, $route, $handler);
    }

    public function group(): RouteGroup
    {
        return $this->routeGroup->group();
    }

    public function middleware($middleware): Router
    {
        $this->middleware->add($middleware);
        return $this;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->middleware->process($request, new RouterHandler(new GroupCountBased($this->routeGroup->getData())));
    }

    public function exportable(Exporter $exporter, $level = 0): string
    {
        $data = $this->routeGroup->getData();
        return $exporter->export([
            $data,
            $this->middleware,
        ]);
    }
}
