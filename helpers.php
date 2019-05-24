<?php

if (! function_exists('inertia')) {
    function inertia($component, $props)
    {
        return \Inertia\Inertia::render($component, $props);
    }
}