<?php

namespace Inertia;

trait OptionalProps
{
    /**
     * Indicates if the property should be optional.
     */
    protected bool $optional = false;

    /**
     * Mark this property as optional.
     */
    public function optional(): static
    {
        $this->optional = true;

        return $this;
    }

    public function isOptional(): bool
    {
        return $this->optional;
    }
}
