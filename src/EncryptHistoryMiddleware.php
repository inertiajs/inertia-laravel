<?php

namespace Inertia;

use Closure;
use Illuminate\Http\Request;

class EncryptHistoryMiddleware
{
    /**
     * Handle the incoming request.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next)
    {
        Inertia::encryptHistory();

        return $next($request);
    }
}
