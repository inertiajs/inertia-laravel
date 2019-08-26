<?php

namespace Inertia\Tests;

use Illuminate\Support\Facades\Route;

class RouterTest extends TestCase
{
    public function test_inertia_route_macro()
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
