<?php

namespace Inertia;

use Closure;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\RedirectResponse as Redirect;

class Middleware
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if (!$request->header('X-Inertia')) {
            return $response;
        }

        if ($request->method() === 'GET' && $request->header('X-Inertia-Version') !== Inertia::getVersion()) {
            if ($request->hasSession()) {
                $request->session()->reflash();
            }

            return Response::make('', 409, ['X-Inertia-Location' => $request->fullUrl()]);
        }

        if ($response instanceof Redirect && $response->getStatusCode() === 302 && in_array($request->method(), ['PUT', 'PATCH', 'DELETE'])) {
            $response->setStatusCode(303);
        }

        return $response;
    }
}
