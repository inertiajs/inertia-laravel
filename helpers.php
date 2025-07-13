<?php

if (! function_exists('inertia')) {
    /**
     * Inertia helper.
     */
    function inertia(?string $component = null, array|Illuminate\Contracts\Support\Arrayable $props = []): Inertia\ResponseFactory|Inertia\Response
    {
        $instance = Inertia\Inertia::getFacadeRoot();

        if ($component) {
            return $instance->render($component, $props);
        }

        return $instance;
    }
}

if (! function_exists('inertia_location')) {
    /**
     * Inertia location helper.
     */
    function inertia_location(string $url): Symfony\Component\HttpFoundation\Response
    {
        $instance = Inertia\Inertia::getFacadeRoot();

        return $instance->location($url);
    }
}
