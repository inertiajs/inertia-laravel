<?php

namespace Inertia\Tests;

use Inertia\Middleware;
use Illuminate\Support\Facades\App;
use Illuminate\Contracts\Http\Kernel;

class ServiceProviderTest extends TestCase
{
    public function test_middleware_is_registered()
    {
        $kernel = App::make(Kernel::class);

        $this->assertTrue($kernel->hasMiddleware(Middleware::class));
    }
}
