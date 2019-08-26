<?php

namespace Inertia\Tests;

use Illuminate\Http\Request;
use Inertia\Controller;

class ControllerTest extends TestCase
{
    public function test_it_returns_an_inertia_response()
    {
        $request = new Request();
        $request->headers->set('X-Inertia-Partial-Component', 'Component');
        $controller = new Controller();

        $response = $controller('Component', ['prop1' => true, 'prop2' => false])->toResponse($request);


        $this->assertEquals('app', $response->name());
        $this->assertEquals([
            'page' => [
                'component' => 'Component',
                'props' => [
                    'prop1' => true,
                    'prop2' => false,
                ],
                'url' => '',
                'version' => null,
            ]
        ], $response->getData());
    }
}