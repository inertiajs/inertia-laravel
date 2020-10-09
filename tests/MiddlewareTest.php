<?php

namespace Inertia\Tests;

use Illuminate\Foundation\Testing\TestResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Inertia\Inertia;
use Inertia\Tests\middleware\ExampleMiddleware;

class MiddlewareTest extends TestCase
{
    public function test_the_version_is_optional()
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);

        $response = $this->makeMockResponse($request);

        $response->assertSuccessful();
        $response->assertJson(['component' => 'User/Edit']);
    }

    public function test_the_version_can_be_a_number()
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Version' => '1597347897973']);

        $response = $this->makeMockResponse($request, 1597347897973);

        $response->assertSuccessful();
        $response->assertJson(['component' => 'User/Edit']);
    }

    public function test_the_version_can_be_a_string()
    {
        $request = Request::create('/user/edit', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Version' => 'foo-version']);

        $response = $this->makeMockResponse($request, 'foo-version');

        $response->assertSuccessful();
        $response->assertJson(['component' => 'User/Edit']);
    }

    public function test_it_will_instruct_inertia_to_reload_on_a_version_mismatch()
    {
        Inertia::version(1234);

        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Version' => 4321]);

        $response = $this->makeMockResponse($request);

        $response->assertStatus(409);
        $response->assertHeader('X-Inertia-Location', $request->fullUrl());
        self::assertEmpty($response->content());
    }

    public function test_validation_errors_are_registered_as_of_default()
    {
        Route::middleware(ExampleMiddleware::class)->get('/', function () {
            $this->assertInstanceOf(\Closure::class, Inertia::getShared('errors'));
        });

        $this->withoutExceptionHandling()->get('/');
    }

    public function test_validation_errors_can_be_empty()
    {
        Route::middleware(ExampleMiddleware::class)->get('/', function () {
            $errors = Inertia::getShared('errors')();

            $this->assertIsObject($errors);
            $this->assertEmpty(get_object_vars($errors));
        });

        $this->withoutExceptionHandling()->get('/');
    }

    public function test_validation_errors_are_returned_in_the_correct_format()
    {
        Session::put('errors', (new ViewErrorBag())->put('default', new MessageBag([
            'name' => 'The name field is required.',
            'email' => 'Not a valid email address.',
        ])));

        Route::middleware(ExampleMiddleware::class)->get('/', function () {
            $errors = Inertia::getShared('errors')();

            $this->assertIsObject($errors);
            $this->assertSame('The name field is required.', $errors->name);
            $this->assertSame('Not a valid email address.', $errors->email);
        });

        $this->withoutExceptionHandling()->get('/');
    }

    public function test_validation_errors_with_named_error_bags_are_scoped()
    {
        Session::put('errors', (new ViewErrorBag())->put('example', new MessageBag([
            'name' => 'The name field is required.',
            'email' => 'Not a valid email address.',
        ])));

        Route::middleware(ExampleMiddleware::class)->get('/', function () {
            $errors = Inertia::getShared('errors')();

            $this->assertIsObject($errors);
            $this->assertSame('The name field is required.', $errors->example->name);
            $this->assertSame('Not a valid email address.', $errors->example->email);
        });

        $this->withoutExceptionHandling()->get('/');
    }

    public function test_default_validation_errors_can_be_overwritten()
    {
        Session::put('errors', (new ViewErrorBag())->put('example', new MessageBag([
            'name' => 'The name field is required.',
            'email' => 'Not a valid email address.',
        ])));

        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);

        $response = $this->makeMockResponse($request, null, ['errors' => 'foo']);

        $response->assertJson([
            'props' => [
                'errors' => 'foo',
            ],
        ]);
    }

    private function makeMockResponse($request, $version = null, $shared = [])
    {
        $response = (new ExampleMiddleware($version, $shared))->handle($request, function ($request) {
            return Inertia::render('User/Edit', ['user' => ['name' => 'Jonathan']])->toResponse($request);
        });

        return TestResponse::fromBaseResponse($response);
    }
}
