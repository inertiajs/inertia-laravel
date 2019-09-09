<?php

namespace Inertia;

use Illuminate\Http\Request;

class Controller
{
    public function __invoke(Request $request)
    {
        return Inertia::render(
            $request->route()->defaults['component'],
            $request->route()->defaults['props']
        );
    }
}
