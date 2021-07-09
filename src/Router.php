<?php

namespace Fas\Routing;

use Composer\Autoload\ClassLoader;
use Exception;
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

    public function save($filename, $preload = null): void
    {
        $path = dirname($filename);
        $tempfile = tempnam(dirname($filename), 'fas-routing');
        @chmod($tempfile, 0666);
        $exporter = new Exporter();
        $exporter->setAttribute('fas-routing-cache-path', $path);
        $exporter->setAttribute('fas-routing-preload', []);
        $exported = $exporter->export($this);
        file_put_contents($tempfile, '<?php return ' . $exported . ';');
        @chmod($tempfile, 0666);
        rename($tempfile, $filename);
        @chmod($filename, 0666);

        if ($preload) {
            $files = $exporter->getAttribute('fas-routing-preload', []);
            $this->savePreload($preload, array_keys($files));
        }
    }

    private function savePreload(string $filename, array $classFiles): void
    {
        foreach (get_declared_classes() as $className) {
            if (strpos($className, 'ComposerAutoloader') === 0) {
                $classLoader = $className::getLoader();
                break;
            }
        }
        if (empty($classLoader)) {
            throw new Exception("Cannot locate class loader");
        }

        $files = [];
        $files[] = $classLoader->findFile(\Psr\Container\ContainerInterface::class);
        $files[] = $classLoader->findFile(\Psr\Http\Message\MessageInterface::class);
        $files[] = $classLoader->findFile(\Psr\Http\Message\ServerRequestInterface::class);
        $files[] = $classLoader->findFile(\Psr\Http\Message\RequestInterface::class);
        $files[] = $classLoader->findFile(\Psr\Http\Message\ResponseInterface::class);
        $files[] = $classLoader->findFile(\Psr\Http\Message\UriInterface::class);
        $files[] = $classLoader->findFile(\Psr\Http\Message\StreamInterface::class);
        $files[] = $classLoader->findFile(\Psr\Http\Server\MiddlewareInterface::class);
        $files[] = $classLoader->findFile(\Psr\Http\Server\RequestHandlerInterface::class);
        $files[] = $classLoader->findFile(\Fas\Autowire\ReferenceTrackerInterface::class);

        $files[] = $classLoader->findFile(\Fas\Autowire\Autowire::class);
        $files[] = $classLoader->findFile(\Fas\Autowire\Container::class);

        $files[] = $classLoader->findFile(\Fas\Routing\CachedRequestHandler::class);
        $files[] = $classLoader->findFile(\Fas\Routing\CachedRouterHandler::class);
        $files[] = $classLoader->findFile(\Fas\Routing\CachedRouter::class);
        $files[] = $classLoader->findFile(\Fas\Routing\CachedMiddleware::class);
        $files[] = $classLoader->findFile(\Fas\Routing\HttpException::class);

        $files[] = $classLoader->findFile(\FastRoute\Dispatcher::class);
        $files[] = $classLoader->findFile(\FastRoute\Dispatcher\RegexBasedAbstract::class);
        $files[] = $classLoader->findFile(\FastRoute\Dispatcher\GroupCountBased::class);

        $files = array_merge($files, $classFiles);
        ob_start();
        include __DIR__ . '/preload.template.php';
        $preload = ob_get_contents();
        ob_end_clean();

        $tempfile = tempnam(dirname($filename), 'fas-routing');
        @chmod($tempfile, 0666);
        file_put_contents($tempfile, '<?php ' . $preload);
        @chmod($tempfile, 0666);
        rename($tempfile, $filename);
        @chmod($filename, 0666);
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
        return $exporter->export(
            [
            $this->routeGroup,
            $this->middleware,
            ]
        );
    }
}
