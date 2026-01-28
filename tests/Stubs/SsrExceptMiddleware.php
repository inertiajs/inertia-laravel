<?php

namespace Inertia\Tests\Stubs;

use Inertia\Middleware;

class SsrExceptMiddleware extends Middleware
{
    /**
     * The paths that should be excluded from server-side rendering.
     *
     * @var array<int, string>
     */
    protected $withoutSsr = [
        'admin/*',
        'nova/*',
    ];
}
