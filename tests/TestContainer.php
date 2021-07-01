<?php

namespace Fas\Routing\Tests;

use Psr\Container\ContainerInterface;

class TestContainer implements ContainerInterface
{
    public function has(string $id): bool
    {
        return class_exists($id);
    }

    public function get(string $id)
    {
        return new $id();
    }
}
