<?php

namespace Inertia\Tests;

use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Inertia\ExceptionResponse;
use Inertia\Inertia;
use Inertia\Middleware;

class ExceptionResponseTest extends TestCase
{
    public function test_exceptions_are_not_intercepted_without_handler(): void
    {
        Route::middleware([StartSession::class, Middleware::class])->get('/', function () {
            abort(500);
        });

        $response = $this->get('/', ['X-Inertia' => 'true']);

        $response->assertStatus(500);
        $this->assertFalse($response->headers->has('X-Inertia'));
    }

    public function test_exceptions_can_render_inertia_pages(): void
    {
        Inertia::handleExceptionsUsing(function (ExceptionResponse $response) {
            return $response->render('Error', [
                'status' => $response->statusCode(),
                'message' => $response->exception->getMessage(),
            ]);
        });

        Route::middleware([StartSession::class, Middleware::class])->get('/', function () {
            abort(500, 'Something went wrong');
        });

        $response = $this->get('/', ['X-Inertia' => 'true']);

        $response->assertStatus(500);
        $response->assertJson([
            'component' => 'Error',
            'props' => [
                'status' => 500,
                'message' => 'Something went wrong',
            ],
        ]);
    }

    public function test_exceptions_can_return_redirects(): void
    {
        Inertia::handleExceptionsUsing(function (ExceptionResponse $response) {
            if ($response->statusCode() === 419) {
                return redirect()->to('/login');
            }

            return $response->render('Error', ['status' => $response->statusCode()]);
        });

        Route::middleware([StartSession::class, Middleware::class])->get('/', function () {
            abort(419);
        });

        $response = $this->get('/', ['X-Inertia' => 'true']);

        $response->assertRedirect('/login');
    }

    public function test_exceptions_can_fall_through_to_default(): void
    {
        Inertia::handleExceptionsUsing(function (ExceptionResponse $response) {
            if ($response->statusCode() === 500) {
                return null;
            }

            return $response->render('Error', ['status' => $response->statusCode()]);
        });

        Route::middleware([StartSession::class, Middleware::class])->get('/', function () {
            abort(500);
        });

        $response = $this->get('/', ['X-Inertia' => 'true']);

        $response->assertStatus(500);
        $this->assertFalse($response->headers->has('X-Inertia'));
    }

    public function test_exceptions_with_shared_data(): void
    {
        $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
        $kernel->appendMiddlewareToGroup('web', Stubs\HttpExceptionMiddleware::class);

        Inertia::handleExceptionsUsing(function (ExceptionResponse $response) {
            return $response->render('Error', ['status' => $response->statusCode()])
                ->withSharedData();
        });

        $response = $this->get('/non-existent-page', ['X-Inertia' => 'true']);

        $response->assertStatus(404);
        $response->assertJson([
            'component' => 'Error',
            'props' => [
                'status' => 404,
                'appName' => 'My App',
            ],
        ]);
    }

    public function test_exceptions_with_shared_data_from_explicit_middleware(): void
    {
        Inertia::handleExceptionsUsing(function (ExceptionResponse $response) {
            return $response->render('Error', ['status' => $response->statusCode()])
                ->withSharedData(Stubs\HttpExceptionMiddleware::class);
        });

        Route::middleware([StartSession::class, Middleware::class])->get('/', function () {
            abort(500);
        });

        $response = $this->get('/', ['X-Inertia' => 'true']);

        $response->assertStatus(500);
        $response->assertJson([
            'component' => 'Error',
            'props' => [
                'status' => 500,
                'appName' => 'My App',
            ],
        ]);
    }

    public function test_exceptions_without_shared_data(): void
    {
        $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
        $kernel->appendMiddlewareToGroup('web', Stubs\HttpExceptionMiddleware::class);

        Inertia::handleExceptionsUsing(function (ExceptionResponse $response) {
            return $response->render('Error', ['status' => $response->statusCode()]);
        });

        $response = $this->get('/non-existent-page', ['X-Inertia' => 'true']);

        $response->assertStatus(404);
        $page = $response->json();
        $this->assertSame('Error', $page['component']);
        $this->assertSame(404, $page['props']['status']);
        $this->assertArrayNotHasKey('appName', $page['props']);
    }

    public function test_exceptions_with_custom_root_view(): void
    {
        Inertia::handleExceptionsUsing(function (ExceptionResponse $response) {
            return $response->render('Error', ['status' => $response->statusCode()])
                ->rootView('custom');
        });

        Route::middleware([StartSession::class, Middleware::class])->get('/', function () {
            abort(500);
        });

        $response = $this->get('/', ['X-Inertia' => 'true']);

        $response->assertStatus(500);
        $response->assertJson(['component' => 'Error']);
    }

    public function test_exceptions_outside_middleware_are_handled(): void
    {
        $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
        $kernel->appendMiddlewareToGroup('web', Middleware::class);

        Inertia::handleExceptionsUsing(function (ExceptionResponse $response) {
            return $response->render('Error', ['status' => $response->statusCode()]);
        });

        $response = $this->get('/non-existent-page', ['X-Inertia' => 'true']);

        $response->assertStatus(404);
        $response->assertJson([
            'component' => 'Error',
            'props' => [
                'status' => 404,
            ],
        ]);
    }
}
