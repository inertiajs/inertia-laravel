<?php

use Illuminate\Contracts\Support\Arrayable;
use Inertia\Inertia;
use Inertia\Response;
use Inertia\ResponseFactory;

if (! function_exists('inertia')) {
    /**
     * Inertia helper.
     *
     * @param  null|string  $component
     * @param  array|Arrayable  $props
     * @return ($component is null ? ResponseFactory : Response)
     */
    function inertia($component = null, $props = [])
    {
        $instance = Inertia::getFacadeRoot();

        if ($component) {
            return $instance->render($component, $props);
        }

        return $instance;
    }
}

if (! function_exists('inertia_location')) {
    /**
     * Inertia location helper.
     *
     * @param  string  url
     * @return Symfony\Component\HttpFoundation\Response
     */
    function inertia_location($url)
    {
        $instance = Inertia::getFacadeRoot();

        return $instance->location($url);
    }
}
