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
}
