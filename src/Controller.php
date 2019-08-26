<?php

namespace Inertia;

class Controller
{
    public function __invoke($component, $props)
    {
        return Inertia::render($component, $props);
    }
}