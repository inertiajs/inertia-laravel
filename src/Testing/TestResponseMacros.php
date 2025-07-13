<?php

namespace Inertia\Testing;

use Closure;
use Illuminate\Support\Arr;

class TestResponseMacros
{
    /**
     * Assert that the response is an Inertia response.
     */
    public function assertInertia()
    {
        return function (?Closure $callback = null) {
            $assert = AssertableInertia::fromTestResponse($this);

            if (is_null($callback)) {
                return $this;
            }

            $callback($assert);

            return $this;
        };
    }

    /**
     * Get the Inertia page data as an array.
     */
    public function inertiaPage()
    {
        return fn () => AssertableInertia::fromTestResponse($this)->toArray();
    }

    /**
     * Get specific props from the Inertia response.
     */
    public function inertiaProps()
    {
        return fn (?string $propName = null) => Arr::get($this->inertiaPage()['props'], $propName);
    }
}
