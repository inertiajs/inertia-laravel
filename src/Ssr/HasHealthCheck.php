<?php

namespace Inertia\Ssr;

interface HasHealthCheck
{
    /**
     * Determine if the SSR server is healthy and responsive.
     */
    public function isHealthy(): bool;
}
