<?php

namespace Fas\Routing;

class CachedRouteGroup extends RouteGroup
{
    public function __construct(array $dispatchData)
    {
        $this->dispatchData = $dispatchData;
    }

    public function getData()
    {
        return $this->dispatchData;
    }

}
