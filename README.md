

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
