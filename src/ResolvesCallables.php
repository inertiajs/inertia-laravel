<?php

namespace Inertia;

use Illuminate\Support\Facades\App;

trait ResolvesCallables
{
    /**
     * Call the given value if callable and inject its dependencies.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function resolveCallable($value)
    {
        return $this->useAsCallable($value) ? App::call($value) : $value;
    }

    /**
     * Determine if the given value is callable, but not a string.
     *
     * @param  mixed  $value
     */
    protected function useAsCallable($value): bool
    {
        return ! is_string($value) && is_callable($value);
    }
}
