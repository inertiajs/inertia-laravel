<?php

namespace Inertia\DevTools\Http;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reflashes the session so the save closing an entry request does not age the flash data. Entries
 * are fetched the moment response headers arrive, racing the redirect the app is about to follow,
 * so the errors a POST flashed for that redirect would be gone before the page could read them.
 */
class PreserveFlashData
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->hasSession()) {
            $request->session()->reflash();
        }

        return $response;
    }
}
