<?php

namespace Inertia\Tests;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware\EnsureGetOnRedirect;
use Inertia\Tests\Stubs\ExampleMiddleware;

class ServiceProviderTest extends TestCase
{
    public function test_blade_directive_is_registered(): void
    {
        $this->assertArrayHasKey('inertia', Blade::getCustomDirectives());
    }

    public function test_request_macro_is_registered(): void
    {
        $request = Request::create('/user/123', 'GET');

        $this->assertFalse($request->inertia());

        $request->headers->add(['X-Inertia' => 'true']);

        $this->assertTrue($request->inertia());
    }

    public function test_route_macro_is_registered(): void
    {
        $route = Route::inertia('/', 'User/Edit', ['user' => ['name' => 'Jonathan']]);
        $routes = Route::getRoutes();

        $this->assertNotEmpty($routes->getRoutes());

        $inertiaRoute = collect($routes->getRoutes())->first(fn ($route) => $route->uri === '/');

        $this->assertEquals($route, $inertiaRoute);
        $this->assertEquals(['GET', 'HEAD'], $inertiaRoute->methods);
        $this->assertEquals('/', $inertiaRoute->uri);
        $this->assertEquals(['uses' => '\Inertia\Controller@__invoke', 'controller' => '\Inertia\Controller'], $inertiaRoute->action);
        $this->assertEquals(['component' => 'User/Edit', 'props' => ['user' => ['name' => 'Jonathan']]], $inertiaRoute->defaults);
    }

    public function test_ensure_get_on_redirect_middleware_is_registered_globally(): void
    {
        /** @var Kernel $kernel */
        $kernel = $this->app->make(HttpKernelContract::class);

        $this->assertTrue($kernel->hasMiddleware(EnsureGetOnRedirect::class));
    }

    public function test_redirect_response_from_rate_limiter_is_converted_to_303(): void
    {
        RateLimiter::for('api', fn () => Limit::perMinute(1)->response(fn () => back()));

        // Needed for the web middleware
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

        Route::middleware(['web', ExampleMiddleware::class, 'throttle:api'])
            ->delete('/foo', fn () => 'ok');

        $this
            ->from('/bar')
            ->delete('/foo', [], ['X-Inertia' => 'true'])
            ->assertOk();

        $this
            ->from('/bar')
            ->delete('/foo', [], ['X-Inertia' => 'true'])
            ->assertRedirect('/bar')
            ->assertStatus(303);
    }
}
