<?php

namespace Inertia\Tests;

use Illuminate\Support\Facades\Route;
use Inertia\Controller;
use Inertia\Tests\middleware\DefaultMiddleware;

class ControllerTest extends TestCase
{
    public function test_controller_returns_an_inertia_response()
    {
        Route::middleware(DefaultMiddleware::class)
            ->get('/', Controller::class)
            ->defaults('component', 'User/Edit')
            ->defaults('props', [
                'user' => ['name' => 'Jonathan'],
            ]);

        $response = $this->get('/');

        $page = $response->viewData('page');
        $this->assertEquals($page, [
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
