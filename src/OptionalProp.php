<?php

namespace Inertia;

use Illuminate\Support\Facades\App;

class OptionalProp implements IgnoreFirstLoad
{
    protected $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function __invoke()
    {
        return App::call($this->callback);
    }
}
