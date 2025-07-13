<?php

namespace Inertia;

use Closure;
use Illuminate\Support\Facades\App;

class LazyProp implements IgnoreFirstLoad
{
    /**
     * Create a new lazy property instance.
     */
    public function __construct(
        protected Closure $callback,
    ) {}

    /**
     * Invoke the property to get its value.
     *
     * Executes the callback and returns the result.
     */
    public function __invoke()
    {
        return App::call($this->callback);
    }
}
