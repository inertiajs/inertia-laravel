<?php

namespace Inertia;

use Illuminate\Http\Request;

class Controller
{
    /**
     * Handle the incoming request and render the Inertia response.
     */
    public function __invoke(Request $request): Response
    {
        return Inertia::render(
            $request->route()->defaults['component'],
            $request->route()->defaults['props']
        );
    }
}
