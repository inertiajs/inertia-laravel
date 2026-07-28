<?php

namespace Inertia\Tests\Stubs;

use Inertia\Middleware;

/**
 * Renders through a root view that has a `</body>` tag, so the devtools id script tag has
 * somewhere to be injected.
 */
class DevToolsRootViewMiddleware extends Middleware
{
    /**
     * @var string
     */
    protected $rootView = 'devtools-app';
}
