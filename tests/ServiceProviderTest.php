<?php

namespace Inertia\Tests;

use Closure;
use Inertia\Inertia;
use Inertia\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Facades\App;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;

class ServiceProviderTest extends TestCase
{
    public function test_blade_directive_is_registered()
    {
        $directives = Blade::getCustomDirectives();

        $this->assertArrayHasKey('inertia', $directives);
        $this->assertEquals('<div id="app" data-page="{{ json_encode($page) }}"></div>', $directives['inertia']());
    }

    public function test_request_macro_is_registered()
    {
        $request = Request::create('/user/123', 'GET');

        $this->assertFalse($request->inertia());

        $request->headers->add(['X-Inertia' => 'true']);

        $this->assertTrue($request->inertia());
    }

    public function test_route_macro_is_registered()
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

    public function test_middleware_is_registered_to_the_web_group()
    {
        $webRoute = Route::middleware('web')->get('/');
        $apiRoute = Route::middleware('api')->get('/');

        $webMiddleware = App::make(Router::class)->gatherRouteMiddleware($webRoute);
        $apiMiddleware = App::make(Router::class)->gatherRouteMiddleware($apiRoute);

        $this->assertContains(Middleware::class, $webMiddleware);
        $this->assertNotContains(Middleware::class, $apiMiddleware);
    }

    public function test_validation_errors_are_registered()
    {
        $this->assertInstanceOf(Closure::class, Inertia::getShared('errors'));
    }

    public function test_validation_errors_can_be_empty()
    {
        $errors = Inertia::getShared('errors')();

        $this->assertIsObject($errors);
        $this->assertEmpty(get_object_vars($errors));
    }

    public function test_validation_errors_are_not_registered_when_already_registered()
    {
        Inertia::share('errors', 'This is a validation error');

        $this->assertSame('This is a validation error', Inertia::getShared('errors'));
    }

    public function test_validation_errors_are_returned_in_the_correct_format()
    {
        Session::put('errors', (new ViewErrorBag())->put('default', new MessageBag([
            'name' => 'The name field is required.',
            'email' => 'Not a valid email address.',
        ])));

        $errors = Inertia::getShared('errors')();

        $this->assertIsObject($errors);
        $this->assertSame('The name field is required.', $errors->name);
        $this->assertSame('Not a valid email address.', $errors->email);
    }

    public function test_validation_errors_with_named_error_bags_are_scoped()
    {
        Session::put('errors', (new ViewErrorBag())->put('example', new MessageBag([
            'name' => 'The name field is required.',
            'email' => 'Not a valid email address.',
        ])));

        $errors = Inertia::getShared('errors')();

        $this->assertIsObject($errors);
        $this->assertSame('The name field is required.', $errors->example->name);
        $this->assertSame('Not a valid email address.', $errors->example->email);
    }
}
