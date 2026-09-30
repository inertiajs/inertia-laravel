<?php

namespace Inertia\Tests\Stubs;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrecognitiveRequestMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->header('Precognition') === 'true') {
            $request->attributes->set('precognitive', true);
        }

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}
