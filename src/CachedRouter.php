<?php

namespace Fas\Routing;

use Fas\Autowire\Autowire;
use FastRoute\Dispatcher\GroupCountBased;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CachedRouter implements RequestHandlerInterface
{
    protected ?ContainerInterface $container;
    protected array $routeGroupData;
    protected array $middlewares;
    protected Autowire $autowire;

    public function __construct(?ContainerInterface $container = null)
    {
        $this->autowire = new Autowire($container);
        $this->container = $this->autowire->getContainer();
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return (new CachedMiddleware($this->container, $this->middlewares))
            ->process($request, new CachedRouterHandler(new GroupCountBased($this->routeGroupData), $this->container));
    }

    public static function load($filename, ?ContainerInterface $container = null): ?CachedRouter
    {
        $loader = @include $filename;
        if (!is_array($loader)) {
            return null;
        }
        [$file, $class] = $loader;
        if (!class_exists($class, false)) {
            require_once $file;
        }
        return new $class($container);
    }
}
