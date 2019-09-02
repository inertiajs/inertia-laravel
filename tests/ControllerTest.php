<?php

namespace Inertia\Tests;

use Inertia\Response;
use Inertia\Controller;
use Illuminate\Http\Request;

class ControllerTest extends TestCase
{
    public function test_controller_returns_an_inertia_response()
    {
        $response = (new Controller())('User/Edit', ['user' => ['name' => 'Jonathan']]);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals([
            'page' => [
                'component' => 'User/Edit',
                'props' => ['user' => ['name' => 'Jonathan']],
                'url' => '',
                'version' => null,
            ],
        ], $response->toResponse(new Request())->getData());
    }
}
