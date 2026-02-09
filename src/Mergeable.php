<?php

namespace Inertia;

interface Mergeable
{
    /**
     * Mark the property for merging.
     */
    public function merge(): static;

    /**
     * Determine if the property should be merged.
     */
    public function shouldMerge(): bool;

    /**
     * Determine if the property should be deep merged.
     */
    public function shouldDeepMerge(): bool;

    /**
     * Get the properties to match on for merging.
     *
     * @return array<int, string>
     */
    public function matchesOn(): array;

    /**
     * Determine if the property should be appended at the root level.
     */
    public function appendsAtRoot(): bool;

    /**
     * Determine if the property should be prepended at the root level.
     */
    public function prependsAtRoot(): bool;

    /**
     * Get the paths to append when merging.
     *
     * @return array<int, string>
     */
    public function appendsAtPaths(): array;

    /**
     * Get the paths to prepend when merging.
     *
     * @return array<int, string>
     */
    public function prependsAtPaths(): array;
}
