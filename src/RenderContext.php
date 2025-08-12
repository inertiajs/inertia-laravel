<?php

namespace Inertia;

use Illuminate\Http\Request;

class RenderContext
{
    /**
     * Create a new render context instance. The render context provides
     * information about the current Inertia render operation to objects
     * implementing ProvidesInertiaProperties.
     */
    public function __construct(
        public string $component,
        public Request $request
    ) {
        //
    }
}
