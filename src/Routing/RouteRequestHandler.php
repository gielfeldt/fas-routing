<?php

namespace Fas\Routing;

use Fas\DI\Autowire;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RouteRequestHandler implements RequestHandlerInterface
{
    public function __construct(Autowire $autowire, $handler, array $args)
    {
        $this->autowire = $autowire;
        $this->args = $args;
        $this->handler = $handler;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->autowire->call($this->handler, ['request' => $request] + $this->args);
    }
}