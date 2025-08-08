<?php

namespace Inertia\Testing;

use Closure;
use Illuminate\Support\Arr;

class TestResponseMacros
{
    /**
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
