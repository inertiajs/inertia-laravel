<?php

namespace Inertia\Tests;

use Inertia\Inertia;
use Inertia\LazyProp;
use Inertia\AlwaysProp;
use Inertia\ResponseFactory;
use Illuminate\Http\Response;
use Illuminate\Session\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Request;
use Illuminate\Session\NullSessionHandler;
use Inertia\Tests\Stubs\ExampleMiddleware;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Session\Middleware\StartSession;

class ResponseFactoryTest extends TestCase
{
    public function test_can_macro(): void
    {
        $factory = new ResponseFactory();
        $factory->macro('foo', function () {
            return 'bar';
        });

        $this->assertEquals('bar', $factory->foo());
    }

    public function test_location_response_for_inertia_requests(): void
    {
        Request::macro('inertia', function () {
            return true;
        });

        $response = (new ResponseFactory())->location('https://inertiajs.com');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_CONFLICT, $response->getStatusCode());
        $this->assertEquals('https://inertiajs.com', $response->headers->get('X-Inertia-Location'));
    }

    public function test_location_response_for_non_inertia_requests(): void
    {
        Request::macro('inertia', function () {
            return false;
        });

        $response = (new ResponseFactory())->location('https://inertiajs.com');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertEquals('https://inertiajs.com', $response->headers->get('location'));
    }

    public function test_location_response_for_inertia_requests_using_redirect_response(): void
    {
        Request::macro('inertia', function () {
            return true;
        });

        $redirect = new RedirectResponse('https://inertiajs.com');
        $response = (new ResponseFactory())->location($redirect);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(409, $response->getStatusCode());
        $this->assertEquals('https://inertiajs.com', $response->headers->get('X-Inertia-Location'));
    }

    public function test_location_response_for_non_inertia_requests_using_redirect_response(): void
    {
        $redirect = new RedirectResponse('https://inertiajs.com');
        $response = (new ResponseFactory())->location($redirect);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertEquals('https://inertiajs.com', $response->headers->get('location'));
    }

    public function test_location_redirects_are_not_modified(): void
    {
        $response = (new ResponseFactory())->location('/foo');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertEquals('/foo', $response->headers->get('location'));
    }

    public function test_location_response_for_non_inertia_requests_using_redirect_response_with_existing_session_and_request_properties(): void
    {
        $redirect = new RedirectResponse('https://inertiajs.com');
        $redirect->setSession($session = new Store('test', new NullSessionHandler()));
        $redirect->setRequest($request = new HttpRequest());
        $response = (new ResponseFactory())->location($redirect);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertEquals('https://inertiajs.com', $response->headers->get('location'));
        $this->assertSame($session, $response->getSession());
        $this->assertSame($request, $response->getRequest());
        $this->assertSame($response, $redirect);
    }

    public function test_the_version_can_be_a_closure(): void
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

    public function test_shared_data_can_be_shared_from_anywhere(): void
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

    public function test_can_flush_shared_data(): void
    {
        Inertia::share('foo', 'bar');
        $this->assertSame(['foo' => 'bar'], Inertia::getShared());
        Inertia::flushShared();
        $this->assertSame([], Inertia::getShared());
    }

    public function test_can_create_lazy_prop(): void
    {
        $factory = new ResponseFactory();
        $lazyProp = $factory->lazy(function () {
            return 'A lazy value';
        });

        $this->assertInstanceOf(LazyProp::class, $lazyProp);
    }

    public function test_can_create_always_prop(): void
    {
        $factory = new ResponseFactory();
        $alwaysProp = $factory->always(function () {
            return 'An always value';
        });

        $this->assertInstanceOf(AlwaysProp::class, $alwaysProp);
    }

    public function test_will_accept_arrayabe_props()
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            Inertia::share('foo', 'bar');

            return Inertia::render('User/Edit', new class() implements Arrayable {
                public function toArray()
                {
                    return [
                        'foo' => 'bar',
                    ];
                }
            });
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
}
