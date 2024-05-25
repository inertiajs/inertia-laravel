<?php

namespace Inertia\Tests;

use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Inertia\Controller;
use Inertia\Tests\Stubs\ExampleMiddleware;

class ControllerTest extends TestCase
{
    public function test_controller_returns_an_inertia_response(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])
            ->get('/', Controller::class)
            ->defaults('component', 'User/Edit')
            ->defaults('props', [
                'user' => ['name' => 'Jonathan'],
            ]);

        $response = $this->get('/');

        $this->assertEquals($response->viewData('page'), [
            'component' => 'User/Edit',
            'props' => [
                'user' => ['name' => 'Jonathan'],
                'errors' => (object) [],
            ],
            'url' => '/',
            'version' => '',
        ]);
    }
}
