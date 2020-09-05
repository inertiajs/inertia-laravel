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
            $route = new Route(['GET'], '/', ['\Inertia\Controller', '__invoke']);
            $route->defaults('component', 'User/Edit');
            $route->defaults('props', [
                'user' => ['name' => 'Jonathan'],
            ]);

            return $route;
        });

        $response = (new Controller())($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals([
            'page' => [
                'component' => 'User/Edit',
                'props' => [
                    'user' => ['name' => 'Jonathan'],
                    'errors' => (object) [],
                ],
                'url' => '',
                'version' => null,
            ],
        ], $response->toResponse(new Request())->getOriginalContent()->getData());
    }
}
