<?php

namespace Inertia\Tests;

use Closure;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\LazyProp;
use Inertia\ResponseFactory;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Request;
use Inertia\Tests\Stubs\ExampleMiddleware;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\NullSessionHandler;
use Illuminate\Session\Store;

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
        $redirect->setSession($session = new Store('test', new NullSessionHandler));
        $redirect->setRequest($request = new HttpRequest);
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

    public function test_can_resolve_shared_data(): void
    {
        Inertia::share('string', 'string-value');
        Inertia::share('array', ['array-value']);
        Inertia::share('closure', function () {
            return 'closure-value';
        });
        App::instance(BoundInstance::class, new BoundInstance('lazy-value'));
        Inertia::share('lazy', Inertia::lazy(function (BoundInstance $instance) {
            return  $instance->value;
        }));

        $this->assertSame('string-value', Inertia::resolveShared('string'));
        $this->assertSame(['array-value'], Inertia::resolveShared('array'));
        $this->assertSame('closure-value', Inertia::resolveShared('closure'));
        $this->assertSame('lazy-value', Inertia::resolveShared('lazy'));
    }

    public function test_can_merge_with_shared_data(): void
    {
        // Lazy prop closures are called by the app. Want to make sure we are
        // still able to resolve from the container.
        App::bind(BoundInstance::class, function () {
            return new BoundInstance('value');
        });

        $merged = Inertia::mergeProps(['eager-value'], ['merged-value']);
        $this->assertIsArray($merged);
        $this->assertSame(['eager-value', 'merged-value'], $merged);

        $merged = Inertia::mergeProps(['eager' => 'value'], ['merged' => 'value']);
        $this->assertIsArray($merged);
        $this->assertSame(['eager' => 'value', 'merged' => 'value'], $merged);

        $merged = Inertia::mergeProps(function () {
            return ['closure-value'];
        }, ['merged-value']);
        $this->assertInstanceOf(Closure::class, $merged);
        $this->assertSame(['closure-value', 'merged-value'], $merged());

        $merged = Inertia::mergeProps(function () {
            return ['closure' => 'value'];
        }, ['merged' => 'value']);
        $this->assertInstanceOf(Closure::class, $merged);
        $this->assertSame(['closure' => 'value', 'merged' => 'value'], $merged());

        $merged = Inertia::mergeProps(Inertia::lazy(function (BoundInstance $instance) {
            return ["lazy-{$instance->value}"];
        }), ['merged-value']);
        $this->assertInstanceOf(LazyProp::class, $merged);
        $this->assertSame(['lazy-value', 'merged-value'], $merged());

        $merged = Inertia::mergeProps(Inertia::lazy(function (BoundInstance $instance) {
            return ['lazy' => $instance->value];
        }), ['merged' => 'value']);
        $this->assertInstanceOf(LazyProp::class, $merged);
        $this->assertSame(['lazy' => 'value', 'merged' => 'value'], $merged());
    }

    public function test_closures_are_not_invoked_while_merging()
    {
        $invocations = 0;

        $merged = Inertia::mergeProps(function () use (&$invocations) {
            $invocations++;

            return ['closure-value'];
        }, ['merged-value']);

        $this->assertInstanceOf(Closure::class, $merged);
        $this->assertSame(0, $invocations);
        $this->assertSame(['closure-value', 'merged-value'], $merged());
        $this->assertSame(1, $invocations);

        $invocations = 0;

        $merged = Inertia::mergeProps(Inertia::lazy(function () use (&$invocations) {
            $invocations++;

            return ['closure-value'];
        }), ['merged-value']);

        $this->assertInstanceOf(LazyProp::class, $merged);
        $this->assertSame(0, $invocations);
        $this->assertSame(['closure-value', 'merged-value'], $merged());
        $this->assertSame(1, $invocations);
    }

    public function test_can_get_shared_and_merge_with_new_props(): void
    {
        Inertia::share('eager-list', ['eager-value']);
        Inertia::share('eager-associative', ['eager' => 'value']);
        Inertia::share('closure-list', function () {
            return ['closure-value'];
        });
        Inertia::share('closure-associative', function () {
            return ['closure' => 'value'];
        });
        $alreadyResolved = false;
        App::bind(BoundInstance::class, function () use (&$alreadyResolved) {
            if (! $alreadyResolved) {
                $alreadyResolved = true;

                return new BoundInstance(['lazy-value']);
            } else {
                return new BoundInstance(['lazy' => 'value']);
            }
        });
        Inertia::share('lazy-list', Inertia::lazy(function (BoundInstance $instance) {
            return $instance->value;
        }));
        Inertia::share('lazy-associative', Inertia::lazy(function (BoundInstance $instance) {
            return $instance->value;
        }));

        $merged = Inertia::getSharedAndMergeProps('eager-list', ['merged-value']);
        $this->assertIsArray($merged);
        $this->assertSame(['eager-value', 'merged-value'], $merged);

        $merged = Inertia::getSharedAndMergeProps('eager-associative', ['merged' => 'value']);
        $this->assertIsArray($merged);
        $this->assertSame(['eager' => 'value', 'merged' => 'value'], $merged);

        $merged = Inertia::getSharedAndMergeProps('closure-list', ['merged-value']);
        $this->assertInstanceOf(Closure::class, $merged);
        $this->assertSame(['closure-value', 'merged-value'], $merged());

        $merged = Inertia::getSharedAndMergeProps('closure-associative', ['merged' => 'value']);
        $this->assertInstanceOf(Closure::class, $merged);
        $this->assertSame(['closure' => 'value', 'merged' => 'value'], $merged());

        $merged = Inertia::getSharedAndMergeProps('lazy-list', ['merged-value']);
        $this->assertInstanceOf(LazyProp::class, $merged);
        $this->assertSame(['lazy-value', 'merged-value'], $merged());

        $merged = Inertia::getSharedAndMergeProps('lazy-associative', ['merged' => 'value']);
        $this->assertInstanceOf(LazyProp::class, $merged);
        $this->assertSame(['lazy' => 'value', 'merged' => 'value'], $merged());
    }

    public function test_can_create_lazy_prop(): void
    {
        $factory = new ResponseFactory();
        $lazyProp = $factory->lazy(function () {
            return 'A lazy value';
        });

        $this->assertInstanceOf(LazyProp::class, $lazyProp);
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

class BoundInstance
{
    public $value;

    public function __construct($value)
    {
        $this->value = $value;
    }
}
