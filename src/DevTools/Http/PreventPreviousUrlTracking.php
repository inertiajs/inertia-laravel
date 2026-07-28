<?php

namespace Inertia\DevTools\Http;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks an entry request as an XHR so the session middleware does not record it as the app's
 * previous URL, which would send the app's next `back()` redirect to a devtools endpoint.
 * Set here rather than trusted to the caller: `curl` and CLI tooling might not send it.
 */
class PreventPreviousUrlTracking
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        return $next($request);
    }
}
