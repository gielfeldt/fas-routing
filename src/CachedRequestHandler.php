<?php

namespace Fas\Routing;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CachedRequestHandler implements RequestHandlerInterface
{
    public function __construct($handler, array $args, ?ContainerInterface $container = null)
    {
        $this->args = $args;
        $this->handler = $handler;
        $this->container = $container;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return ($this->handler)($this->container, ['request' => $request] + $this->args);
    }
}
