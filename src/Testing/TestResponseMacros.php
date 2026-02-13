<?php

namespace Inertia\Testing;

use Closure;
use Illuminate\Support\Arr;
use Inertia\SessionKey;

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

    /**
     * Register the 'assertInertiaFlash' macro for TestResponse.
     *
     * @return Closure
     */
    public function assertInertiaFlash()
    {
        return function (string $key, mixed $expected = null) {
            /** @phpstan-ignore-next-line */
            $flash = $this->session()->get(SessionKey::FlashData->value, []);

            func_num_args() > 1
                ? AssertableInertia::assertFlashHas($flash, $key, $expected)
                : AssertableInertia::assertFlashHas($flash, $key);

            return $this;
        };
    }

    /**
     * Register the 'assertInertiaFlashMissing' macro for TestResponse.
     *
     * @return Closure
     */
    public function assertInertiaFlashMissing()
    {
        return function (string $key) {
            /** @phpstan-ignore-next-line */
            $flash = $this->session()->get(SessionKey::FlashData->value, []);

            AssertableInertia::assertFlashMissing($flash, $key);

            return $this;
        };
    }
}
