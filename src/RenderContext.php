<?php

namespace Inertia;

use Illuminate\Http\Request;

class RenderContext
{
    public function __construct(
        public string $component,
        public Request $request
    ) {
        //
    }
}
