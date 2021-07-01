<?php

namespace Fas\Routing\Tests;

use Fas\Routing\ErrorResponse;
use Exception;
use Fas\Routing\Router;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

class ErrorResponseTest extends TestCase
{

    public function testCanUseErrorResponseAsMiddleware()
    {
        $factory = new Psr17Factory();

        $router = new Router();
        $router->middleware(new ErrorResponse($factory));

        $router->map('GET', '/fail', function () {
            throw new Exception("I failed");
        });

        $request = $factory->createServerRequest('GET', '/fail');
        $response = $router->handle($request);

        $this->assertStringContainsString("Exception: I failed in", (string) $response->getBody());
    }
}
