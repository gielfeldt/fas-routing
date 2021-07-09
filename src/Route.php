<?php

namespace Fas\Routing;

use Exception;
use Fas\Autowire\Autowire;
use Fas\Exportable\ExportableInterface;
use Fas\Exportable\Exporter;
use Psr\Container\ContainerInterface;
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
        $compiled = $autowire->compileCall($this->callback);

        $handler = $exporter->export($compiled);
        $middlewares = $exporter->export($this->middleware);

        $request = "function request(\\" . ServerRequestInterface::class . '$request): ResponseInterface { ';
        if (empty($this->middleware->getMiddlewares())) {
            $request .= 'return $this->handle($request);';
        } else {
            $request .= 'return (new \\Fas\\Routing\\CachedMiddleware($this->container, self::MIDDLEWARES))->process($request, $this);';
        }
        $request .= " }\n";

        $id = hash('sha256', $handler . $middlewares);
        $class = "route_$id";
        $code = "<?php\n";
        $code .= "use " . ResponseInterface::class . ";\n";
        $code .= "class $class extends \Fas\Routing\CachedRoute {\n";
        $code .= "    const MIDDLEWARES = $middlewares;\n";
        $code .= "    $request\n";
        $code .= "    function handle(\\" . ServerRequestInterface::class . '$request): ResponseInterface { return (' . $handler . ')($this->container, [\'request\' => $request] + $this->args); }';
        $code .= "}\n";

        $cachePath = $exporter->getAttribute('fas-routing-cache-path');
        if (empty($cachePath)) {
            throw new Exception("Could not locate cache path");
        }
        $file = $cachePath . "/route_$id.php";
        $tempfile = tempnam(dirname($file), 'fas-routing-route');
        @chmod($tempfile, 0666);
        file_put_contents($tempfile, $code);
        @chmod($tempfile, 0666);
        rename($tempfile, $file);
        @chmod($file, 0666);
        $preload = $exporter->getAttribute('fas-routing-preload', []);
        $preload[$file] = $file;
        $exporter->setAttribute('fas-routing-preload', $preload);
        return var_export([realpath($file), $class], true);
    }
}
