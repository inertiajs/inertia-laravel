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
     * Determine if the property should be appended or prepended.
     *
     * @return bool
     */
    public function shouldAppend();

    /**
     * Determine if the property should merge at the root level (vs at specific paths).
     *
     * @return bool
     */
    public function shouldMergeAtRootLevel();

    /**
     * Get the paths to append when merging.
     *
     * @return array<int, string>
     */
    public function appendPaths(): array;

    /**
     * Get the paths to prepend when merging.
     *
     * @return array<int, string>
     */
    public function prependPaths(): array;
}
