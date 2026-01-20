<?php

namespace Inertia;

interface Optionable
{
    /**
     * Determine if this property should be optional.
     */
    public function isOptional(): bool;
}
