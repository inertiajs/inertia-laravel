<?php

namespace Inertia;

interface Mergeable
{
    /**
     * @return static
     */
    public function merge();

    /**
     * @return bool
     */
    public function shouldMerge();

    /**
     * @return bool
     */
    public function shouldDeepMerge();

    /**
     * @return array<int, string>
     */
    public function matchesOn();
}
