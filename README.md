[![Build Status](https://github.com/gielfeldt/fas-routing/actions/workflows/test.yml/badge.svg)][4]
![Test Coverage](https://img.shields.io/endpoint?url=https://gist.githubusercontent.com/gielfeldt/0f0d97def8e970cfb455e528c703c506/raw/fas-routing__main.json)

[![Latest Stable Version](https://poser.pugx.org/fas/routing/v/stable.svg)][1]
[![Latest Unstable Version](https://poser.pugx.org/fas/routing/v/unstable.svg)][2]
[![License](https://poser.pugx.org/fas/routing/license.svg)][3]
![Total Downloads](https://poser.pugx.org/fas/routing/downloads.svg)


# Installation

```bash
composer require fas/routing
```


# Usage

An api on top of fastroute.
With autowiring capabilities using a container or not.

## Without a container

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Fas\Routing\Router;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;

$router = new Router;

$router->map('GET', '/hello/[{name}]', function (ResponseFactory $responseFactory, $name = 'nobody') {
    $response = $responseFactory->createResponse(200);
    $response->getBody()->write("Hello: $name");
    return $response;
});

// Handle actual request
$request = ServerRequestFactory::fromGlobals();
$response = $router->handle($request);
(new SapiEmitter)->emit($response);

```

## With a container

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Fas\DI\Container;
use Fas\Routing\Router;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Psr\Http\Message\ResponseFactoryInterface;

$container = new Container;
$container->singleton(ResponseFactoryInterface::class, ResponseFactory::class);

$router = new Router($container);

$router->map('GET', '/hello/[{name}]', function (ResponseFactoryInterface $responseFactory, $name = 'nobody') {
    $response = $responseFactory->createResponse(200);
    $response->getBody()->write("Hello: $name");
    return $response;
});

// Handle actual request
$request = ServerRequestFactory::fromGlobals();
$response = $router->handle($request);
(new SapiEmitter)->emit($response);
```


## Middlewares

Middlewares attached to `Router` are always run, even if the requested route does not exist.
```php
<?php

$router = new Router($container);


$router->middleware(function ($request, $handler) {
    return $some_response || $handler->handle($request);
});
$router->middleware('some_container_entry_that_is_a_psr_middleware');
$router->middleware(['some_container_entry_that_is_an_object', 'method']);
$router->middleware(['some_class_name', 'method']);
$router->middleware([$some_object, 'method']);
$router->middleware($some_object_that_is_a_psr_middleware);
$router->middleware($some_object_that_is_invokable);


```


## Groups
Groups can be created for attaching middlewares to multiple routes.
There's no prefix mechanism available.

Middlewares attached to groups are only run, if the requested route exists.

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use App\AuthMiddleware;
use Fas\Routing\Router;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;

$router = new Router;

// Authenticated routes
$authenticated = $router->group();
$authenticated->middleware(AuthMiddleware::class);

$$authenticated->map('GET', '/hello/[{name}]', function (ResponseFactory $responseFactory, $name = 'nobody') {
    $response = $responseFactory->createResponse(200);
    $response->getBody()->write("Hello: $name");
    return $response;
});


// Un-authenticated routes
$anonymous = $router->group();

$$anonymous->map('GET', '/login', function (ResponseFactory $responseFactory) {
    $response = $responseFactory->createResponse(200);
    $response->getBody()->write("Please login first");
    return $response;
});

// Handle actual request
$request = ServerRequestFactory::fromGlobals();
$response = $router->handle($request);
(new SapiEmitter)->emit($response);

```

## Compiled/cached router with container

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Fas\DI\Container;
use Fas\Routing\Router;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Psr\Http\Message\ResponseFactoryInterface;

$container = new Container;
$container->singleton(ResponseFactoryInterface::class, ResponseFactory::class);

$router = Router::load('/tmp/router.cache.php', $container);
if (!$router) {
    $router = new Router($container);
    $router->map('GET', '/hello/[{name}]', function (ResponseFactoryInterface $responseFactory, $name = 'nobody') {
        $response = $responseFactory->createResponse(200);
        $response->getBody()->write("Hello: $name");
        return $response;
    });
    $router->save('/tmp/router.cache.php');
}

// Handle actual request
$request = ServerRequestFactory::fromGlobals();
$response = $router->handle($request);
(new SapiEmitter)->emit($response);
```


# Cookbook
## Separated container and router creation with error response

Requires some laminas libs (or other psr factories and a response emitter)

```bash
composer require laminas/laminas-diactoros
composer require laminas/laminas-httphandlerrunner
```

/app/public/index.php:
```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Fas\DI\Container;
use Fas\Routing\ErrorResponse;
use Fas\Routing\Router;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;

try {
    $container = Container::load('/app/container.cache.php') ?? require __DIR__ . '/../src/container.php';
    $container->useProxyCache('/app/proxy.cache');

    $router = Router::load('/app/router.cache.php', $container) ?? require __DIR__ . '/../src/router.php';

    // Handle incoming request
    $request = ServerRequestFactory::fromGlobals();
    $response = $router->handle($request);
} catch (Throwable $e) {
    $response = (new ErrorResponse(new ResponseFactory))->createResponse($e);
} finally {
    (new SapiEmitter)->emit($response);
}

```

/app/src/container.php:
```php
<?php

use Fas\DI\Container;
use Laminas\Diactoros\RequestFactory;
use Laminas\Diactoros\ResponseFactory;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;

$container = new Container;

$container->singleton(ResponseFactoryInterface::class, ResponseFactory::class);
$container->singleton(RequestFactoryInterface::class, RequestFactory::class);

return $container;
```

/app/src/router.php:
```php
<?php

use Fas\Routing\Router;
use Psr\Http\Message\ResponseFactoryInterface;

$router = new Router($container);

$router->map('GET', '/hello[/{name}]', function (ResponseFactoryInterface $responseFactory, $name = 'world') {
    $response = $responseFactory->createResponse(200);
    $response->getBody()->write("Hello: $name!");
    return $response;
});

return $router;
```

/app/bin/compile.php
```php
<?php

require __DIR__ . '/../vendor/autoload.php';

// Build container
$container = require __DIR__ . '/../container.php';
@mkdir('/app/proxy.cache', 0777, true);
$proxies = $container->buildProxyCache('/app/proxy.cache');
print "-----------------\nBuilt " . count($proxies) . " proxies\n-----------------\n" . implode("\n", $proxies) . "\n-----------------\n";
$entries = $container->save('/app/container.cache.php');
print "-----------------\nBuilt " . count($entries) . " entries\n-----------------\n" . implode("\n", $entries) . "\n-----------------\n";

// Build routes
$router = require __DIR__ . '/../router.php';
$router->save('/app/router.cache.php');
```

```bash
curl -i http://localhost/hello/there
```

```
HTTP/1.1 200 OK
Date: Fri, 27 May 2021 23:56:30 GMT
Server: Apache/2.4.38 (Debian)
X-Powered-By: PHP/8.0.6
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET
Access-Control-Allow-Headers: x-requested-with, authorization, accept, accept-encoding
Content-Length: 13
Content-Type: text/html; charset=UTF-8

Hello: there!
```

[1]:  https://packagist.org/packages/fas/routing
[2]:  https://packagist.org/packages/fas/routing#dev-main
[3]:  https://github.com/gielfeldt/fas-routing/blob/main/LICENSE.md
[4]:  https://github.com/gielfeldt/fas-routing/actions/workflows/test.yml
