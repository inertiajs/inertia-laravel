<?php

namespace Inertia\Tests\Stubs;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

class StorePartialReloadsMiddleware extends Middleware
{
    /**
     * Determines if the request is a partial reload of the component that was rendered.
     */
    protected function isPartialReload(Request $request, Response $response): bool
    {
        return false;
    }
}
