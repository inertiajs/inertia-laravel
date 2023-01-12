<?php

namespace Inertia\Ssr;

interface Gateway
{
    /**
     * Dispatch the Inertia page to the Server Side Rendering engine.
     */
    public function dispatch(array $page): ?Response;
}
