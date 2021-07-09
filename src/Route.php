<?php

namespace Fas\Routing;

use Exception;
use Fas\Autowire\Autowire;
use Fas\Exportable\ExportableInterface;
use Fas\Exportable\ExportableRaw;
use Fas\Exportable\Exporter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
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
{
    $middlewares = ' . $exporter->export($this->middleware) . ';
    $callback = ' . $exporter->export(new ExportableRaw($autowire->compileCall($this->callback))) . ';
    $middleware = new \\' . CachedMiddleware::class . '($container, $middlewares);
    $handler = new \\' . CachedRequestHandler::class . '($callback, $vars, $container);
    return $middleware->process($request, $handler);
}';
        } else {
            $code = '
{
    $callback = ' . $exporter->export(new ExportableRaw($autowire->compileCall($this->callback))) . ';
    $handler = new \\' . CachedRequestHandler::class . '($callback, $vars, $container);
    return $handler->handle($request);
}';
        }
        $id = hash('sha256', $code);
        $class = "route_$id";
        $code = 'static function handle(\\Psr\\Http\\Message\\ServerRequestInterface $request, array $vars, ?\\Psr\\Container\\ContainerInterface $container) ' . $code;
        $code = "class $class {\n$code\n}\n";
        $cachePath = $exporter->getAttribute('fas-routing-cache-path');
        if (empty($cachePath)) {
            throw new Exception("Could not locate cache path");
        }
        $file = $cachePath . "/route_$id.php";
        $tempfile = tempnam(dirname($file), 'fas-routing-route');
        @chmod($tempfile, 0666);
        file_put_contents($tempfile, "<?php\n$code\n");
        @chmod($tempfile, 0666);
        rename($tempfile, $file);
        @chmod($file, 0666);
        $preload = $exporter->getAttribute('fas-routing-preload', []);
        $preload[$file] = $file;
        $exporter->setAttribute('fas-routing-preload', $preload);
        return var_export([$file, $class], true);
    }
}
