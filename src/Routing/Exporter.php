<?php

namespace Fas\Routing;

use Closure;
use InvalidArgumentException;
use Opis\Closure\ReflectionClosure;
use Psr\Container\ContainerInterface;

class Exporter
{
    public static function var_export($data, ContainerInterface $container = null, $level = 0): string
    {
        if (is_array($data)) {
            $result = "";
            $items = [];
            foreach ($data as $key => $value) {
                $items[] = str_repeat("  ", $level + 1) . var_export($key, true) . ' => ' . static::var_export($value, $container, $level + 1);
            }
            $result = "[\n" . implode(",\n", $items) . "\n" . str_repeat("  ", $level) . "]";
            return $result;
        }
        if ($data instanceof Closure) {
            return (new ReflectionClosure($data))->getCode();
        }
        if ($data instanceof Route) {
            return $data->compile($container);
        }
        if ($data instanceof Raw) {
            return $data->getData();
        }
        if (is_object($data)) {
            throw new InvalidArgumentException("Cannot cache live objects in router " . get_class($data));
        }
        return var_export($data, true);
    }

}
