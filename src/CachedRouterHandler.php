<?php

namespace Fas\Routing;

use FastRoute\Dispatcher;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class CachedRouterHandler implements RequestHandlerInterface
{
    private Dispatcher $dispatcher;
    private ?ContainerInterface $container;

    public function __construct(Dispatcher $dispatcher, ?ContainerInterface $container = null)
    {
        $this->dispatcher = $dispatcher;
        $this->container = $container;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
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
                [$file, $function] = $routeInfo[1];
                $vars = $routeInfo[2];
                try {
                    include_once $file;
                    return $function($request, $vars, $this->container);
                } catch (Throwable $e) {
                    throw $e instanceof HttpException ? $e : new HttpException(500, "Internal server error", $e);
                }
        }
        // Ignore extra defensive coding coverage
        // @codeCoverageIgnoreStart
        throw new HttpException(500, "No route info found");
        // @codeCoverageIgnoreEnd
    }
}
