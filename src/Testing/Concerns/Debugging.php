<?php

namespace Inertia\Testing\Concerns;

/**
 * @deprecated This trait is deprecated and will be removed in a future version.
 * @see https://github.com/inertiajs/inertia-laravel/pull/338
 */
trait Debugging
{
    public function dump(?string $prop = null): self
    {
        dump($this->prop($prop));

        return $this;
    }

    public function dd(?string $prop = null): void
    {
        dd($this->prop($prop));
    }

    abstract protected function prop(?string $key = null);
}
