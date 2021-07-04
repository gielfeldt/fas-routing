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

## Without container setup

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

## With container setup

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Fas\Autowire\Container;
use Fas\Routing\Router;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Psr\Http\Message\ResponseFactoryInterface;

$container = new Container();
$container->set(ResponseFactoryInterface::class, ResponseFactory::class);

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

## Compiled/cached router without container setup

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Fas\Routing\Router;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;

$router = Router::load('/tmp/router.cache.php');
if (!$router) {
    $router = new Router();
    $router->map('GET', '/hello/[{name}]', function (ResponseFactory $responseFactory, $name = 'nobody') {
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

## Compiled/cached with container setup

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Fas\Autowire\Container;
use Fas\Routing\Router;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Psr\Http\Message\ResponseFactoryInterface;

$container = new Container();
$container->set(ResponseFactoryInterface::class, ResponseFactory::class);

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


[1]:  https://packagist.org/packages/fas/routing
[2]:  https://packagist.org/packages/fas/routing#dev-main
[3]:  https://github.com/gielfeldt/fas-routing/blob/main/LICENSE.md
[4]:  https://github.com/gielfeldt/fas-routing/actions/workflows/test.yml
