<?php

namespace Fas\Routing;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

abstract class CachedRoute implements RequestHandlerInterface
{
    protected ContainerInterface $container;
    protected array $args;

    public function __construct(ContainerInterface $container, array $args = [])
    {
        $this->container = $container;
        $this->args = $args;
    }

    abstract function handle(ServerRequestInterface $request): ResponseInterface;
    abstract function request(ServerRequestInterface $request): ResponseInterface;
}
