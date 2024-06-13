<?php

namespace Inertia\Tests;

use LogicException;
use Inertia\Inertia;
use Inertia\AlwaysProp;
use Inertia\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Inertia\Tests\Stubs\ExampleMiddleware;
use Illuminate\Session\Middleware\StartSession;

class MiddlewareTest extends TestCase
{
    public function test_no_response_value_by_default_means_automatically_redirecting_back_for_inertia_requests(): void
    {
        $fooCalled = false;
        Route::middleware(Middleware::class)->put('/', function () use (&$fooCalled) {
            $fooCalled = true;
        });

        $response = $this
            ->from('/foo')
            ->put('/', [], [
                'X-Inertia' => 'true',
                'Content-Type' => 'application/json',
            ]);

        $response->assertRedirect('/foo');
        $response->assertStatus(303);
        $this->assertTrue($fooCalled);
    }

    public function test_no_response_value_can_be_customized_by_overriding_the_middleware_method(): void
    {
        Route::middleware(ExampleMiddleware::class)->get('/', function () {
            // Do nothing..
        });

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('An empty Inertia response was returned.');

        $this
            ->withoutExceptionHandling()
            ->from('/foo')
            ->get('/', [
                'X-Inertia' => 'true',
                'Content-Type' => 'application/json',
            ]);
    }

    public function test_no_response_means_no_response_for_non_inertia_requests(): void
    {
        $fooCalled = false;
        Route::middleware(Middleware::class)->put('/', function () use (&$fooCalled) {
            $fooCalled = true;
        });

        $response = $this
            ->from('/foo')
            ->put('/', [], [
                'Content-Type' => 'application/json',
            ]);

        $response->assertNoContent(200);
        $this->assertTrue($fooCalled);
    }

    public function test_the_version_is_optional(): void
    {
        $this->prepareMockEndpoint();

        $response = $this->get('/', [
            'X-Inertia' => 'true',
        ]);

        $response->assertSuccessful();
        $response->assertJson(['component' => 'User/Edit']);
    }

    public function test_the_version_can_be_a_number(): void
    {
        $this->prepareMockEndpoint($version = 1597347897973);

        $response = $this->get('/', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
        ]);

        $response->assertSuccessful();
        $response->assertJson(['component' => 'User/Edit']);
    }

    public function test_the_version_can_be_a_string(): void
    {
        $this->prepareMockEndpoint($version = 'foo-version');

        $response = $this->get('/', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
        ]);

        $response->assertSuccessful();
        $response->assertJson(['component' => 'User/Edit']);
    }

    public function test_it_will_instruct_inertia_to_reload_on_a_version_mismatch(): void
    {
        $this->prepareMockEndpoint('1234');

        $response = $this->get('/', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => '4321',
        ]);

        $response->assertStatus(409);
        $response->assertHeader('X-Inertia-Location', $this->baseUrl);
        self::assertEmpty($response->getContent());
    }

    public function test_validation_errors_are_registered_as_of_default(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            $this->assertInstanceOf(AlwaysProp::class, Inertia::getShared('errors'));
        });

        $this->withoutExceptionHandling()->get('/');
    }

    public function test_validation_errors_can_be_empty(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            $errors = Inertia::getShared('errors')();

            $this->assertIsObject($errors);
            $this->assertEmpty(get_object_vars($errors));
        });

        $this->withoutExceptionHandling()->get('/');
    }

    public function test_validation_errors_are_returned_in_the_correct_format(): void
    {
        Session::put('errors', (new ViewErrorBag())->put('default', new MessageBag([
            'name' => 'The name field is required.',
            'email' => 'Not a valid email address.',
        ])));

        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            $errors = Inertia::getShared('errors')();

            $this->assertIsObject($errors);
            $this->assertSame('The name field is required.', $errors->name);
            $this->assertSame('Not a valid email address.', $errors->email);
        });

        $this->withoutExceptionHandling()->get('/');
    }

    public function test_validation_errors_with_named_error_bags_are_scoped(): void
    {
        Session::put('errors', (new ViewErrorBag())->put('example', new MessageBag([
            'name' => 'The name field is required.',
            'email' => 'Not a valid email address.',
        ])));

        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            $errors = Inertia::getShared('errors')();

            $this->assertIsObject($errors);
            $this->assertSame('The name field is required.', $errors->example->name);
            $this->assertSame('Not a valid email address.', $errors->example->email);
        });

        $this->withoutExceptionHandling()->get('/');
    }

    public function test_default_validation_errors_can_be_overwritten(): void
    {
        Session::put('errors', (new ViewErrorBag())->put('example', new MessageBag([
            'name' => 'The name field is required.',
            'email' => 'Not a valid email address.',
        ])));

        $this->prepareMockEndpoint(null, ['errors' => 'foo']);
        $response = $this->get('/', ['X-Inertia' => 'true']);

        $response->assertJson([
            'props' => [
                'errors' => 'foo',
            ],
        ]);
    }

    public function test_validation_errors_are_scoped_to_error_bag_header(): void
    {
        Session::put('errors', (new ViewErrorBag())->put('default', new MessageBag([
            'name' => 'The name field is required.',
            'email' => 'Not a valid email address.',
        ])));

        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            $errors = Inertia::getShared('errors')();

            $this->assertIsObject($errors);
            $this->assertSame('The name field is required.', $errors->example->name);
            $this->assertSame('Not a valid email address.', $errors->example->email);
        });

        $this->withoutExceptionHandling()->get('/', ['X-Inertia-Error-Bag' => 'example']);
    }

    public function test_middleware_can_change_the_root_view_via_a_property(): void
    {
        $this->prepareMockEndpoint(null, [], new class() extends Middleware {
            protected $rootView = 'welcome';
        });

        $response = $this->get('/');
        $response->assertOk();
        $response->assertViewIs('welcome');
    }

    public function test_middleware_can_change_the_root_view_by_overriding_the_rootview_method(): void
    {
        $this->prepareMockEndpoint(null, [], new class() extends Middleware {
            public function rootView(Request $request): string
            {
                return 'welcome';
            }
        });

        $response = $this->get('/');
        $response->assertOk();
        $response->assertViewIs('welcome');
    }

    private function prepareMockEndpoint($version = null, $shared = [], $middleware = null): \Illuminate\Routing\Route
    {
        if (is_null($middleware)) {
            $middleware = new ExampleMiddleware($version, $shared);
        }

        return Route::middleware(StartSession::class)->get('/', function (Request $request) use ($middleware) {
            return $middleware->handle($request, function ($request) {
                return Inertia::render('User/Edit', ['user' => ['name' => 'Jonathan']])->toResponse($request);
            });
        });
    }
}
