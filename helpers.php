<?php

if (! function_exists('inertia')) {
    function inertia($component = null, $props = [])
    {
        $factory = new \Inertia\ResponseFactory();

        if ($component) {
            return $factory->render($component, $props);
        }

        return $factory;
    }
}