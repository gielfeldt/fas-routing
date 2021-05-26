<?php

namespace Fas\Routing;

use Fas\Exportable\Exporter;
use FastRoute\Dispatcher\GroupCountBased;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class Router
{
    private ?ContainerInterface $container;
    private RouteGroup $routeGroup;

    public function __construct(?ContainerInterface $container = null, ?RouteGroup $routeGroup = null)
    {
        $this->container = $container;
        $this->routeGroup = $routeGroup ?? new RouteGroup(null, $container);
    }

    public static function load($filename, ?ContainerInterface $container = null): ?Router
    {
        $dispatchData = @include $filename;
        if (!is_array($dispatchData)) {
            return null;
        }
        $router = new Router($container, new CachedRouteGroup($dispatchData));
        return $router;
    }

    public function save($filename): void
    {
        $data = $this->routeGroup->getData();
        $tempfile = tempnam(dirname($filename), 'routegroup');
        $exporter = new Exporter();
        file_put_contents($tempfile, '<?php return ' . $exporter->export($data) . ';');
        rename($tempfile, $filename);
    }

    public function map($httpMethod, $route, $handler): Route
    {
        return $this->routeGroup->map($httpMethod, $route, $handler);
    }

    public function group(callable $callback = null): RouteGroup
    {
        return $this->routeGroup->group($callback);
    }

    public function middleware($middleware): RouteGroup
    {
        return $this->routeGroup->middleware($middleware);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->dispatcher = $this->dispatcher ?? new GroupCountBased($this->routeGroup->getData());

        $httpMethod = $request->getMethod();
        $path = $request->getUri()->getPath();

        $routeInfo = $this->dispatcher->dispatch($httpMethod, $path);
        switch ($routeInfo[0]) {
            case \FastRoute\Dispatcher::NOT_FOUND:
                throw new HttpException(404, "Not found ($path)");
            case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                $allowedMethods = $routeInfo[1];
                $message = "allowed methods: " . implode(',', $allowedMethods);
                throw new HttpException(405, $message);
            case \FastRoute\Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $vars = $routeInfo[2];
                try {
                    return $handler($request, $vars, $this->container);
                } catch (Throwable $e) {
                    throw $e instanceof HttpException ? $e : new HttpException(500, "Internal server error", $e);
                }
        }
        throw new HttpException(500, "No route info found");
    }
}
