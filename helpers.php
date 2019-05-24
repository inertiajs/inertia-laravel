<?php

if (! function_exists('inertia')) {
    function inertia($component = null, $props = [])
    {
        if ($component) {
            return (new \Inertia\ResponseFactory())->render($component, $props);
        }

        return new \Inertia\ResponseFactory();
    }
}