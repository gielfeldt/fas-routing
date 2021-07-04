<?php

namespace Fas\Routing;

use Fas\Routing\HttpException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class ErrorResponse implements MiddlewareInterface
{
    private ResponseFactoryInterface $responseFactory;

    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->responseFactory = $responseFactory;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $e) {
            return $this->createResponse($e);
        }
    }

    public function createResponse(Throwable $e)
    {
        $code = $e instanceof HttpException ? $e->getCode() : 500;
        $response = $this->responseFactory->createResponse($code, $e->getMessage());
        $response->getBody()->write('<pre>' . (string) $e . '</pre>');
        return $response;
    }
}
