<?php

namespace Inertia\Tests\Stubs;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HttpExceptionMiddleware extends Middleware
{
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'appName' => 'My App',
        ]);
    }
}
