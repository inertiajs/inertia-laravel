<?php

namespace Inertia;

use Illuminate\Support\Facades\App;

class OptionalProp implements IgnoreFirstLoad
{
    /**
     * @var callable
     */
    protected $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * @return mixed
     */
    public function __invoke()
    {
        return App::call($this->callback);
    }
}
