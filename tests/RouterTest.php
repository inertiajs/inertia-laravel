<?php

namespace Inertia\Tests;

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;

class RouterTest extends TestCase
{
    public function test_configuring_route_via_macro()
    {
        /** @var \Illuminate\Routing\Route $route */
        $route = Route::inertia('/', 'Component', ['prop1' => true, 'prop2' => false]);

        /** @var RouteCollection $collection */
        $collection = Route::getRoutes();

        $this->assertNotEmpty($collection->getRoutes());
        $this->assertEquals($route, $collection->getRoutes()[0]);

        $this->assertEquals(['GET', 'HEAD'], $route->methods);

        $this->assertEquals('/', $route->uri);

        $this->assertEquals([
            'uses' => '\Inertia\Controller@__invoke',
            'controller' => '\Inertia\Controller',
        ], $route->action);

        $this->assertEquals([
            'component' => 'Component',
            'props' => ['prop1' => true, 'prop2' => false],
        ], $route->defaults);
    }
}