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
        $this->assertEquals($route, $routes->getRoutes()[0]);
        $this->assertEquals(['GET', 'HEAD'], $route->methods);
        $this->assertEquals('/', $route->uri);
        $this->assertEquals(['uses' => '\Inertia\Controller@__invoke', 'controller' => '\Inertia\Controller'], $route->action);
        $this->assertEquals(['component' => 'User/Edit', 'props' => ['user' => ['name' => 'Jonathan']]], $route->defaults);
    }
}
