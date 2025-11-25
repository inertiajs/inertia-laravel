<?php

namespace Inertia;

trait OnceableProp
{
    /**
     * Indicates if the property should be evaluated only once.
     */
    protected bool $once = false;

    /**
     * Mark the property to be evaluated only once.
     */
    public function once(): static
    {
        $this->once = true;

        return $this;
    }

    /**
     * Determine if the property should be evaluated only once.
     */
    public function shouldResolveOnce(): bool
    {
        return $this->once;
    }
}
