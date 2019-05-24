<?php

if (! function_exists('inertia')) {
    function inertia($component, $props, $rootView = 'app', $version = null)
    {
        return new \Inertia\Response($component, $props, $rootView, $version);
    }
}