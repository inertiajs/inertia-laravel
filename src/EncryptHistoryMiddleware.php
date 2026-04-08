<?php

namespace Inertia;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EncryptHistoryMiddleware
{
    /**
     * Handle the incoming request and enable history encryption. This middleware
     * enables encryption of the browser history state, providing additional
     * security for sensitive data in Inertia responses.
     *
     * @return Response
     */
    public function handle(Request $request, Closure $next)
    {
        Inertia::encryptHistory();

        return $next($request);
    }
}
