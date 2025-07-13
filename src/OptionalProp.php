<?php

namespace Inertia;

use Closure;
use Illuminate\Support\Facades\App;

class OptionalProp implements IgnoreFirstLoad
{
    /**
     * Create a new optional property instance.
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
