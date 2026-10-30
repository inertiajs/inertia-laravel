<?php

namespace Inertia\Ssr;

use Closure;

interface ConfiguresSsrRequests
{
    /**
     * Configure the HTTP request that is sent to the SSR server.
     */
    public function configureRequestUsing(?Closure $callback = null): void;
}
