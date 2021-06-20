<?php

namespace Fas\Routing;

use Fas\DI\Autowire;
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

    private Middleware $middleware;

    public function __construct(?ContainerInterface $container = null, ?RouteGroup $routeGroup = null)
    {
        $this->container = $container;
        $this->routeGroup = $routeGroup ?? new RouteGroup(null, $container);
        $this->middleware = new Middleware($container);
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
        $container = $this->container;
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

        $data = $this->routeGroup->getData();
        return $exporter->export([
            $data,
            $middlewares,
        ]);
    }
}
