<?php

if (! function_exists('inertia')) {
    /**
     * Inertia helper.
     *
     * @param  null|string  $component
     * @param  array|\Illuminate\Contracts\Support\Arrayable  $props
     * @return ($component is null ? \Inertia\ResponseFactory : \Inertia\Response)
     */
    function inertia($component = null, $props = [])
    {
        $instance = \Inertia\Inertia::getFacadeRoot();

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
     * @return \Symfony\Component\HttpFoundation\Response
     */
    function inertia_location($url)
    {
        $instance = \Inertia\Inertia::getFacadeRoot();

        return $instance->location($url);
    }
}
