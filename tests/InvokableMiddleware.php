<?php

namespace Fas\Routing\Tests;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

class InvokableMiddleware
{

    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return TestMiddleware::staticMiddleware($request, $handler);
    }

}