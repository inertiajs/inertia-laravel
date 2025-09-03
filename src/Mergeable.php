<?php

namespace Inertia;

interface Mergeable
{
    /**
     * Mark the property for merging.
     *
     * @return static
     */
    public function merge();

    /**
     * Determine if the property should be merged.
     *
     * @return bool
     */
    public function shouldMerge();

    /**
     * Determine if the property should be deep merged.
     *
     * @return bool
     */
    public function shouldDeepMerge();

    /**
     * Get the properties to match on for merging.
     *
     * @return array<int, string>
     */
    public function matchesOn();

    /**
     * Determine if the property should be appended at the root level.
     *
     * @return bool
     */
    public function appendsAtRoot();

    /**
     * Determine if the property should be prepended at the root level.
     *
     * @return bool
     */
    public function prependsAtRoot();

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
