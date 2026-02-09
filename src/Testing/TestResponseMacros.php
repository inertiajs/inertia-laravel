<?php

namespace Inertia\Testing;

use Closure;
use Illuminate\Support\Arr;

class TestResponseMacros
{
    /**
     * Register the 'assertInertia' macro for TestResponse.
     */
    public function assertInertia(): Closure
    {
        return function (?Closure $callback = null): self {
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
     */
    public function inertiaPage(): Closure
    {
        return function (): array {
            /** @phpstan-ignore-next-line */
            return AssertableInertia::fromTestResponse($this)->toArray();
        };
    }

    /**
     * Register the 'inertiaProps' macro for TestResponse.
     */
    public function inertiaProps(): Closure
    {
        return function (?string $propName = null): mixed {
            /** @phpstan-ignore-next-line */
            $page = AssertableInertia::fromTestResponse($this)->toArray();

            return Arr::get($page['props'], $propName);
        };
    }
}
