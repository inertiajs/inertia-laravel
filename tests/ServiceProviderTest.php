<?php

namespace Inertia\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

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
}
