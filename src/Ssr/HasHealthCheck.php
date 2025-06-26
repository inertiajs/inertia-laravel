<?php

namespace Inertia\Ssr;

interface HasHealthCheck
{
    /**
     * Determine if the SSR server is healthy.
     */
    public function isHealthy(): bool;
}
