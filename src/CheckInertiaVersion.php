<?php

namespace Inertia;

use Closure;
use Illuminate\Support\Facades\Response;

class CheckInertiaVersion
{
    public function handle($request, Closure $next)
    {
        if ($request->method() === 'GET'
            && $request->header('X-Inertia')
            && $request->header('X-Inertia-Version') !== Inertia::getVersion()
        ) {
            return Response::make('', 409, ['X-Inertia-Location' => $request->url()]);
        }

        return $next($request);
    }
}
