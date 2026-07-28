<?php

namespace Inertia\DevTools\Http;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class Authorize
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->allows($request)) {
            return $next($request);
        }

        return response()->json(['message' => 'Forbidden.'], 403);
    }

    /**
     * The local environment is always allowed, since a failing gate would lock a developer
     * out of their own devtools. Everywhere else access is granted only by the configured
     * gate, which decides for the authenticated user.
     */
    protected function allows(Request $request): bool
    {
        if (app()->environment('local')) {
            return true;
        }

        $gate = config('inertia.devtools.gate');

        return is_string($gate) && $gate !== '' && Gate::forUser($request->user())->check($gate);
    }
}
