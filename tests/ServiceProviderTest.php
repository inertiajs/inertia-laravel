<?php

namespace Inertia\Tests;

use Closure;
use Inertia\Inertia;
use Inertia\Middleware;
use Illuminate\Http\Request;
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

    public function test_middleware_is_registered()
    {
        $kernel = App::make(Kernel::class);

        $this->assertTrue($kernel->hasMiddleware(Middleware::class));
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

    public function test_validation_errors_can_be_an_array()
    {
        Session::put('errors', [
            'name' => 'The name field is required.',
            'email' => 'The email must be a valid email address.',
        ]);

        $errors = Inertia::getShared('errors')();

        $this->assertIsObject($errors);
        $this->assertSame('The name field is required.', $errors->name);
        $this->assertSame('The email must be a valid email address.', $errors->email);
    }

    public function test_validation_exceptions_can_be_a_message_bag()
    {
        Session::put('errors', new MessageBag([
            'name' => 'The name field is required.',
            'email' => 'The email must be a valid email address.',
        ]));

        $errors = Inertia::getShared('errors')();

        $this->assertIsObject($errors);
        $this->assertSame('The name field is required.', $errors->name);
        $this->assertSame('The email must be a valid email address.', $errors->email);
    }

    public function test_validation_exceptions_can_be_an_error_bag()
    {
        Session::put('errors', (new ViewErrorBag())->put('default', new MessageBag([
            'name' => 'The name field is required.',
            'email' => 'The email must be a valid email address.',
        ])));

        $errors = Inertia::getShared('errors')();

        $this->assertIsObject($errors);
        $this->assertSame('The name field is required.', $errors->name);
        $this->assertSame('The email must be a valid email address.', $errors->email);
    }

    public function test_validation_exceptions_can_be_multiple_error_bags()
    {
        Session::put('errors', tap(new ViewErrorBag(), function ($errorBags) {
            $errorBags->put('default', new MessageBag(['name' => 'The name field is required.']));
            $errorBags->put('example', new MessageBag(['email' => 'The email must be a valid email address.']));
        }));

        $errors = Inertia::getShared('errors')();

        $this->assertIsObject($errors);
        $this->assertSame('The name field is required.', $errors->default['name']);
        $this->assertSame('The email must be a valid email address.', $errors->example['email']);
    }

    public function test_validation_exceptions_will_be_empty_when_an_invalid_value_was_set_to_the_session()
    {
        Session::put('errors', new Request());
        $errors = Inertia::getShared('errors')();

        $this->assertIsObject($errors);
        $this->assertEmpty(get_object_vars($errors));
    }
}
