<?php

namespace Inertia;

interface Deferrable
{
    /**
     * Determine if this property should be deferred.
     */
    public function shouldDefer(): bool;

    /**
     * Get the defer group for this property.
     */
    public function group(): string;
}
