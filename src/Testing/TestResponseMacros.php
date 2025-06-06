<?php

namespace Inertia\Testing;

use Closure;
use Illuminate\Support\Arr;

class TestResponseMacros
{
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

    public function inertiaPage()
    {
        return function () {
            return AssertableInertia::fromTestResponse($this)->toArray();
        };
    }

    public function inertiaProps()
    {
        return function (?string $propName = null) {
            return Arr::get($this->inertiaPage()['props'], $propName);
        };
    }
}
