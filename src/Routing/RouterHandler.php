<?php

namespace Fas\Routing;

use FastRoute\Dispatcher;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class RouterHandler implements RequestHandlerInterface
{
    public function __construct(Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
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
                $route = $routeInfo[1];
                $vars = $routeInfo[2];
                try {
                    $route->setArguments($vars);
                    return $route->handle($request);
                    //return $handler($request, $vars, $this->container);
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