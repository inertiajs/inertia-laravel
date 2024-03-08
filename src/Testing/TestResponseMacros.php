<?php

namespace Inertia\Testing;

use Closure;

class TestResponseMacros
{
    public function assertInertia()
    {
        return function (Closure $callback = null) {
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
}
