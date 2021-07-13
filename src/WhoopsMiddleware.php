<?php

namespace Fas\Routing;

use Fas\Routing\HttpException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Whoops\Handler\HandlerInterface;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\PlainTextHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Handler\XmlResponseHandler;
use Whoops\Run;
use Whoops\RunInterface;

class WhoopsMiddleware implements MiddlewareInterface
{
    protected ResponseFactoryInterface $responseFactory;
    protected bool $includeStackTrace = false;

    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->responseFactory = $responseFactory;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $t) {
            $format = $this->getPreferredFormat($request->getHeaderLine('Accept'));
            $format = $format === 'text/html' && !$this->includeStackTrace ? 'text/plain' : $format;
            $code = $this->getCode($t);

            $whoops = $this->getWhoops($format);
            $response = $this->responseFactory->createResponse($code, $t->getMessage());
            $response->getBody()->write($whoops->handleException($t));
            return $response
                ->withHeader('Content-Type', $format);
        }
    }

    public function includeStackTrace(bool $includeStackTrace = true)
    {
        $this->includeStackTrace = $includeStackTrace;
    }

    protected function getWhoops(string $format): RunInterface
    {
        $whoops = new Run();
        $whoops->allowQuit(false);
        $whoops->writeToOutput(false);
        $whoops->pushHandler($this->getHandler($format));
        return $whoops;
    }

    protected function getCode($t): int
    {
        return $t instanceof HttpException ? $t->getCode() : 500;
    }

    protected function getHandler(string $format): HandlerInterface
    {
        switch ($format) {
            case 'application/json':
                $handler = new JsonResponseHandler();
                $handler->addTraceToOutput($this->includeStackTrace);
                return $handler;
            case 'text/html':
                $handler = new PrettyPageHandler();
                return $handler;
            case 'text/xml':
                $handler = new XmlResponseHandler();
                $handler->addTraceToOutput($this->includeStackTrace);
                return $handler;
            case 'text/plain':
            default:
                $handler = new PlainTextHandler();
                $handler->addTraceToOutput($this->includeStackTrace);
                return $handler;
        }
    }

    protected function getPreferredFormat(string $accept): string
    {
        $formats = [
            'application/json' => ['application/json'],
            'text/html' => ['text/html'],
            'text/xml' => ['text/xml'],
            'text/plain' => ['text/plain', 'text/css', 'text/javascript'],
        ];

        foreach ($formats as $format => $mimes) {
            foreach ($mimes as $mime) {
                if (stripos($accept, $mime) !== false) {
                    return $format;
                }
            }
        }

        return 'text/plain';
    }
}
