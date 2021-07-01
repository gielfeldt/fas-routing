<?php

namespace Fas\Routing\Tests;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class TestMiddleware implements MiddlewareInterface
{

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return self::staticMiddleware($request, $handler);
    }

    public function methodMiddleware(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return self::staticMiddleware($request, $handler);
    }

    public static function staticMiddleware(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $middleware = (int) $request->getAttribute('middleware', 0);
        $request = $request->withAttribute('middleware', $middleware + 1);
        return $handler->handle($request);
    }
}
