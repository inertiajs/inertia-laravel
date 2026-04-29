<?php

namespace Inertia;

interface Rescuable
{
    /**
     * Determine if resolution errors should be rescued.
     */
    public function shouldRescue(): bool;
}
