<?php

namespace Fas\Routing;

class Raw {
    private $data;

    public function __construct(string $data)
    {
        $this->data = $data;
    }

    public function getData()
    {
        return $this->data;
    }

}
