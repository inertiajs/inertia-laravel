<?php

namespace Inertia\Tests;

use Inertia\Response;
use Inertia\Controller;

class ControllerTest extends TestCase
{
    public function test_controller_returns_an_inertia_response()
    {
        $inertiaResponse = (new Controller())('User/Edit', ['user' => ['name' => 'Jonathan']]);

        $this->assertInstanceOf(Response::class, $inertiaResponse);
    }
}
