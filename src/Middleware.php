<?php

namespace Fas\Routing;

use Exception;
use Fas\Autowire\Autowire;
use Fas\Exportable\ExportableInterface;
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
            return $this->container->get($middleware)->process($request, $this);
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
                $middleware = $autowire->compileCall([$middleware, 'process']);
            } else {
                $middleware = $autowire->compileCall($middleware);
            }

            $code = (string) $middleware;
            $id = hash('sha256', $code);
            $class = "middleware_$id";
            $code = 'static function handle(\\Psr\\Container\\ContainerInterface $container, array $args = []) { return (' . $code . ')($container, $args); }';
            $code = "class $class {\n$code\n}\n";
            $cachePath = $exporter->getAttribute('fas-routing-cache-path');
            if (empty($cachePath)) {
                throw new Exception("Could not locate cache path");
            }
            $file = $cachePath . "/middleware_$id.php";
            $tempfile = tempnam(dirname($file), 'fas-routing-route');
            @chmod($tempfile, 0666);
            file_put_contents($tempfile, "<?php\n$code\n");
            @chmod($tempfile, 0666);
            rename($tempfile, $file);
            @chmod($file, 0666);
            $preload = $exporter->getAttribute('fas-routing-preload', []);
            $preload[$file] = $file;
            $exporter->setAttribute('fas-routing-preload', $preload);

            $middlewares[] = [realpath($file), $class];
        }

        return $exporter->export($middlewares);
    }
}
