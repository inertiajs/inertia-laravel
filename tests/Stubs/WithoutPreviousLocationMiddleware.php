<?php

namespace Inertia\Tests\Stubs;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

class WithoutPreviousLocationMiddleware extends Middleware
{
    /**
     * Determines if the visit should be stored as the previous location.
     */
    public function shouldStoreCurrentUrl(Request $request, Response $response): bool
    {
        return false;
    }
}
