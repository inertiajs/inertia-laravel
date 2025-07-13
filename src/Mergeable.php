<?php

namespace Inertia;

interface Mergeable
{
    /**
     * Enable merging for this object.
     */
    public function merge(): static;

    /**
     * Check if this object should be merged.
     */
    public function shouldMerge(): bool;
}
