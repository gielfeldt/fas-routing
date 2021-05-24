<?php

namespace Fas\Routing;

use Exception;
use Throwable;

class HttpException extends Exception
{
    public function __construct(int $statusCode, string $reasonPhrase, ?Throwable $previous = null)
    {
        parent::__construct($reasonPhrase, $statusCode, $previous);
    }
}