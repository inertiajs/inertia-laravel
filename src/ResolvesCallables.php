<?php

namespace Inertia;

use Illuminate\Support\Facades\App;

trait ResolvesCallables
{
    /**
     * Call the given value if callable and inject its dependencies.
     */
    protected function resolveCallable(mixed $value): mixed
    {
        return $this->useAsCallable($value) ? App::call($value) : $value;
    }

    /**
     * Determine if the given value is callable, but not a string.
     */
    protected function useAsCallable(mixed $value): bool
    {
        return ! is_string($value) && is_callable($value);
    }
}
