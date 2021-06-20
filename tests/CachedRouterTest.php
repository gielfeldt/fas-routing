<?php

namespace Fas\Routing\Tests;

use Exception;
use Fas\Routing\HttpException;
use Fas\Routing\Router;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CachedRouterTest extends TestCase
{

    private function middlewareAdder($value)
    {
        return static function (ServerRequestInterface $request, RequestHandlerInterface $handler) use ($value) {
            $middleware = $request->getAttribute('middleware', '');
            $request = $request->withAttribute('middleware', $middleware . '.' . $value);
            return $handler->handle($request);
        };
    }

    public function testCanMapStaticGetRoute()
    {
        $router = new Router();
        $router->map('GET', '/static', function ($str = 'abc') {
            $response = (new Psr17Factory)->createResponse(200);
            $response->getBody()->write($str);
            return $response;
        });

        $filename = tempnam(sys_get_temp_dir(), 'fas-routing-test');
        $router->save($filename);
        print file_get_contents($filename);
        $router = Router::load($filename);
        unlink($filename);

        $request = (new Psr17Factory)->createServerRequest('GET', '/static');
        $response = $router->handle($request);

        $this->assertEquals("abc", (string) $response->getBody());
    }

    public function testCanMapDynamicGetRoute()
    {
        $router = new Router();
        $router->map('GET', '/static/{str}', function ($str = 'abc') {
            $response = (new Psr17Factory)->createResponse(200);
            $response->getBody()->write($str);
            return $response;
        });

        $filename = tempnam(sys_get_temp_dir(), 'fas-routing-test');
        $router->save($filename);
        $router = Router::load($filename);
        unlink($filename);

        $request = (new Psr17Factory)->createServerRequest('GET', '/static/testdyn');
        $response = $router->handle($request);

        $this->assertEquals("testdyn", (string) $response->getBody());
    }

    public function testMiddlewareIsFifo()
    {
        $router = new Router();
        $group = $router->group();
        $route = $group->map('GET', '/static', function ($request) {
            $response = (new Psr17Factory)->createResponse(200);
            $response->getBody()->write($request->getAttribute('middleware'));
            return $response;
        });

        $route->middleware($this->middlewareAdder("route5"));
        $route->middleware($this->middlewareAdder("route6"));
        $group->middleware($this->middlewareAdder("route3"));
        $group->middleware($this->middlewareAdder("route4"));
        $router->middleware($this->middlewareAdder("route1"));
        $router->middleware($this->middlewareAdder("route2"));

        $filename = tempnam(sys_get_temp_dir(), 'fas-routing-test');
        $router->save($filename);
        $router = Router::load($filename);
        unlink($filename);

        $request = (new Psr17Factory)->createServerRequest('GET', '/static');
        $response = $router->handle($request);

        $this->assertEquals(".route1.route2.route3.route4.route5.route6", (string) $response->getBody());
    }

    public function testWillThrowExceptionIfMethodNotAllowed()
    {
        $router = new Router();
        $router->map('GET', '/only-get', function ($request) {
            $response = (new Psr17Factory)->createResponse(200);
            $response->getBody()->write($request->getAttribute('middleware'));
            return $response;
        });

        $filename = tempnam(sys_get_temp_dir(), 'fas-routing-test');
        $router->save($filename);
        $router = Router::load($filename);
        unlink($filename);

        $request = (new Psr17Factory)->createServerRequest('POST', '/only-get');

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(405);
        $router->handle($request);
    }

    public function testWillThrowExceptionIfRouteNotFound()
    {
        $router = new Router();

        $filename = tempnam(sys_get_temp_dir(), 'fas-routing-test');
        $router->save($filename);
        $router = Router::load($filename);
        unlink($filename);

        $request = (new Psr17Factory)->createServerRequest('GET', '/static');

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);
        $router->handle($request);
    }

    public function testWillThrowExceptionIfHandlerFails()
    {
        $router = new Router();
        $router->map('GET', '/fail', function ($request) {
            throw new Exception('failed', 123);
        });

        $filename = tempnam(sys_get_temp_dir(), 'fas-routing-test');
        $router->save($filename);
        $router = Router::load($filename);
        unlink($filename);

        $request = (new Psr17Factory)->createServerRequest('GET', '/fail');

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(500);
        $this->expectExceptionMessage('Internal server error');
        $router->handle($request);
    }

    public function testCanAutowireMiddlewares()
    {
        $router = new Router();
        $router->map('GET', '/static', function ($request) {
            $response = (new Psr17Factory)->createResponse(200);
            $response->getBody()->write((string) $request->getAttribute('middleware'));
            return $response;
        });

        $router->middleware(TestMiddleware::class);
        $router->middleware([TestMiddleware::class, 'staticMiddleware']);
        $router->middleware([TestMiddleware::class, 'methodMiddleware']);
        $router->middleware(InvokableMiddleware::class);
        $router->middleware(function (ServerRequestInterface $request, RequestHandlerInterface $handler) {
            return TestMiddleware::staticMiddleware($request, $handler);
        });

        $filename = tempnam(sys_get_temp_dir(), 'fas-routing-test');
        $router->save($filename);
        $router = Router::load($filename);
        unlink($filename);

        $request = (new Psr17Factory)->createServerRequest('GET', '/static');
        $response = $router->handle($request);

        $this->assertEquals("5", (string) $response->getBody());
    }

    public function testCanAutowireMiddlewaresUsingContainer()
    {
        $container = new TestContainer();
        $router = new Router($container);
        $router->map('GET', '/static', function ($request) {
            $response = (new Psr17Factory)->createResponse(200);
            $response->getBody()->write((string) $request->getAttribute('middleware'));
            return $response;
        });

        $router->middleware(TestMiddleware::class);
        $router->middleware([TestMiddleware::class, 'staticMiddleware']);
        $router->middleware([TestMiddleware::class, 'methodMiddleware']);
        $router->middleware(InvokableMiddleware::class);
        $router->middleware(function (ServerRequestInterface $request, RequestHandlerInterface $handler) {
            return TestMiddleware::staticMiddleware($request, $handler);
        });

        $filename = tempnam(sys_get_temp_dir(), 'fas-routing-test');
        $router->save($filename);
        $router = Router::load($filename, $container);
        unlink($filename);

        $request = (new Psr17Factory)->createServerRequest('GET', '/static');
        $response = $router->handle($request);

        $this->assertEquals("5", (string) $response->getBody());
    }

    public function testMissingCacheReturnsNull()
    {
        $router = Router::load('/tmp/somefilethatdoesnotexist' . uniqid());

        $this->assertNull($router);
    }

}
