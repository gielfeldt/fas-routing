<?php

namespace Fas\Routing;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

interface RouteHandlerInterface
{
    public function handle(ServerRequestInterface $request, array $middlewares, $handler, array $args): ResponseInterface;
    public function callHandler($handler, array $args = []);
    public function callMiddleware($middleware, ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface;
}
