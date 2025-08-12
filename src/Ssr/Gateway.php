<?php

namespace Inertia\Ssr;

interface Gateway
{
    /**
     * Dispatch the Inertia page to the SSR engine.
     *
     * @param  array<string, mixed>  $page
     */
    public function dispatch(array $page): ?Response;
}
