<?php

namespace Fas\Routing;

use Exception;
use Fas\Autowire\Autowire;
use Fas\Exportable\ExportableInterface;
use Fas\Exportable\Exporter;
use FastRoute\Dispatcher\GroupCountBased;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
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
        return CachedRouter::load($filename, $container);
    }

    public function save($filename, $preload = null): void
    {
        $path = dirname($filename);
        $exporter = new Exporter();
        $exporter->setAttribute('fas-routing-cache-path', $path);
        $exporter->setAttribute('fas-routing-preload', []);
        $exported = $exporter->export($this);

        $className = 'router_' . hash('sha256', $exported);
        $classFilename = "$path/$className.php";

        $data = null;
        eval("\$data = $exported;");
        $routeData = $exporter->export($data[0]);
        $middlewares = $exporter->export($data[1]);

        $code = "<?php\n";
        $code .= "class $className extends \\Fas\\Routing\\CachedRouter {\n";
        $code .= '    protected array $routeGroupData = ' . $routeData . ";\n";
        $code .= '    protected array $middlewares = ' . $middlewares . ";\n";
        $code .= "}\n";

        $tempfile = tempnam($path, 'fas-routing');
        @chmod($tempfile, 0666);
        file_put_contents($tempfile, $code);
        @chmod($tempfile, 0666);
        rename($tempfile, $classFilename);
        @chmod($classFilename, 0666);

        $tempfile = tempnam($path, 'fas-routing');
        @chmod($tempfile, 0666);
        file_put_contents($tempfile, '<?php return ' . var_export([realpath($classFilename), $className], true) . ';');
        @chmod($tempfile, 0666);
        rename($tempfile, $filename);
        @chmod($filename, 0666);

        if ($preload) {
            $files = $exporter->getAttribute('fas-routing-preload', []);
            $files[$classFilename] = $classFilename;
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
        $files[] = $classLoader->findFile(\FastRoute\Dispatcher\GroupCountBased::class);
        $files[] = $classLoader->findFile(\FastRoute\Dispatcher::class);

        $files[] = $classLoader->findFile(\Fas\Autowire\Autowire::class);
        $files[] = $classLoader->findFile(\Fas\Autowire\Container::class);

        $files[] = $classLoader->findFile(\Fas\Routing\CachedMiddleware::class);
        $files[] = $classLoader->findFile(\Fas\Routing\CachedRouterHandler::class);
        $files[] = $classLoader->findFile(\Fas\Routing\CachedRouter::class);

        $files[] = $classLoader->findFile(\Fas\Routing\HttpException::class);

        $files = array_merge($files, $classFiles);
        $preload = "<?php\n";
        foreach ($files as $file) {
            $preload .= 'require_once(' . var_export(realpath($file), true) . ");\n";
        }

        $tempfile = tempnam(dirname($filename), 'fas-routing');
        @chmod($tempfile, 0666);
        file_put_contents($tempfile, $preload);
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
