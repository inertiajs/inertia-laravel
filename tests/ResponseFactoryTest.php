<?php

namespace Inertia\Tests;

use Illuminate\Http\Response;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\LazyProp;
use Inertia\ResponseFactory;
use Inertia\Tests\Stubs\ExampleMiddleware;

class ResponseFactoryTest extends TestCase
{
    public function test_can_macro()
    {
        $factory = new ResponseFactory();
        $factory->macro('foo', function () {
            return 'bar';
        });

        $this->assertEquals('bar', $factory->foo());
    }

    public function test_location_response()
    {
        $response = (new ResponseFactory())->location('https://inertiajs.com');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(409, $response->getStatusCode());
        $this->assertEquals('https://inertiajs.com', $response->headers->get('X-Inertia-Location'));
    }

    public function test_the_version_can_be_a_closure()
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            $this->assertSame('', Inertia::getVersion());

            Inertia::version(function () {
                return md5('Inertia');
            });

            return Inertia::render('User/Edit');
        });

        $response = $this->withoutExceptionHandling()->get('/', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => 'b19a24ee5c287f42ee1d465dab77ab37',
        ]);

        $response->assertSuccessful();
        $response->assertJson(['component' => 'User/Edit']);
    }

    public function test_shared_data_can_be_shared_from_anywhere()
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            Inertia::share('foo', 'bar');

            return Inertia::render('User/Edit');
        });

        $response = $this->withoutExceptionHandling()->get('/', ['X-Inertia' => 'true']);

        $response->assertSuccessful();
        $response->assertJson([
            'component' => 'User/Edit',
            'props' => [
                'foo' => 'bar',
            ],
        ]);
    }

    public function test_can_create_lazy_prop()
    {
        $factory = new ResponseFactory();
        $lazyProp = $factory->lazy(function () {
            return 'A lazy value';
        });

        $this->assertInstanceOf(LazyProp::class, $lazyProp);
    }
}
