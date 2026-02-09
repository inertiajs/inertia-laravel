<?php

namespace Inertia\Ssr;

interface ExcludesSsrPaths
{
    /**
     * Exclude the given paths from server-side rendering.
     *
     * @param  array<int, string>|string  $paths
     */
    public function except(array|string $paths): void;
}
