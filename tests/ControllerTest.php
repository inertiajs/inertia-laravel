<?php

namespace Inertia\Tests;

use Inertia\Response;
use Inertia\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

class ControllerTest extends TestCase
{
    public function test_controller_returns_an_inertia_response()
    {
        $request = new Request();

        $request->setRouteResolver(static function () {
            $route = new Route(['GET'], '/', [Controller::class, '__invoke']);
            $route->defaults('component', 'User/Edit');
            $route->defaults('props', [
                'user' => ['name' => 'Jonathan'],
            ]);
            return $route;
        });

        $inertiaResponse = (new Controller())($request);

        $this->assertInstanceOf(Response::class, $inertiaResponse);

        $this->assertEquals([
            'page' => [
                'component' => 'User/Edit',
                'props' => ['user' => ['name' => 'Jonathan']],
                'url' => '',
                'version' => null,
            ],
        ], $inertiaResponse->toResponse(new Request())->getOriginalContent()->getData());
    }
}
