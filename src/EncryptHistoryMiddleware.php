<?php

namespace Inertia;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EncryptHistoryMiddleware
{
    /**
     * Handle the incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        Inertia::encryptHistory();

        return $next($request);
    }
}
