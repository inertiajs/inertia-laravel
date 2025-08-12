<?php

namespace Inertia\Testing;

use Closure;
use Illuminate\Support\Arr;

class TestResponseMacros
{
    /**
     * Register the 'assertInertia' macro for TestResponse.
     *
     * @return Closure
     */
    public function assertInertia()
    {
        return function (?Closure $callback = null) {
            /** @phpstan-ignore-next-line */
            $assert = AssertableInertia::fromTestResponse($this);

            if (is_null($callback)) {
                return $this;
            }

            $callback($assert);

            return $this;
        };
    }

    /**
     * Register the 'inertiaPage' macro for TestResponse.
     *
     * @return Closure
     */
    public function inertiaPage()
    {
        return function () {
            /** @phpstan-ignore-next-line */
            return AssertableInertia::fromTestResponse($this)->toArray();
        };
    }

    /**
     * Register the 'inertiaProps' macro for TestResponse.
     *
     * @return Closure
     */
    public function inertiaProps()
    {
        return function (?string $propName = null) {
            /** @phpstan-ignore-next-line */
            $page = AssertableInertia::fromTestResponse($this)->toArray();

            return Arr::get($page['props'], $propName);
        };
    }
}
