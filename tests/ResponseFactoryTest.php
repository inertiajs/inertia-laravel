<?php

namespace Inertia\Tests;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Http\Response;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\NullSessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;
use Inertia\AlwaysProp;
use Inertia\ComponentNotFoundException;
use Inertia\DeferProp;
use Inertia\Inertia;
use Inertia\LazyProp;
use Inertia\MergeProp;
use Inertia\OnceProp;
use Inertia\OptionalProp;
use Inertia\ResponseFactory;
use Inertia\ScrollMetadata;
use Inertia\ScrollProp;
use Inertia\Tests\Stubs\ExampleInertiaPropsProvider;
use Inertia\Tests\Stubs\ExampleMiddleware;

class ResponseFactoryTest extends TestCase
{
    public function test_can_macro(): void
    {
        $factory = new ResponseFactory;
        $factory->macro('foo', function () {
            return 'bar';
        });

        /** @phpstan-ignore-next-line */
        $this->assertEquals('bar', $factory->foo());
    }

    public function test_location_response_for_inertia_requests(): void
    {
        Request::macro('inertia', function () {
            return true;
        });

        $response = (new ResponseFactory)->location('https://inertiajs.com');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_CONFLICT, $response->getStatusCode());
        $this->assertEquals('https://inertiajs.com', $response->headers->get('X-Inertia-Location'));
    }

    public function test_location_response_for_non_inertia_requests(): void
    {
        Request::macro('inertia', function () {
            return false;
        });

        $response = (new ResponseFactory)->location('https://inertiajs.com');

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
        $response = (new ResponseFactory)->location($redirect);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(409, $response->getStatusCode());
        $this->assertEquals('https://inertiajs.com', $response->headers->get('X-Inertia-Location'));
    }

    public function test_location_response_for_non_inertia_requests_using_redirect_response(): void
    {
        $redirect = new RedirectResponse('https://inertiajs.com');
        $response = (new ResponseFactory)->location($redirect);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertEquals('https://inertiajs.com', $response->headers->get('location'));
    }

    public function test_location_redirects_are_not_modified(): void
    {
        $response = (new ResponseFactory)->location('/foo');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertEquals('/foo', $response->headers->get('location'));
    }

    public function test_location_response_for_non_inertia_requests_using_redirect_response_with_existing_session_and_request_properties(): void
    {
        $redirect = new RedirectResponse('https://inertiajs.com');
        $redirect->setSession($session = new Store('test', new NullSessionHandler));
        $redirect->setRequest($request = new HttpRequest);
        $response = (new ResponseFactory)->location($redirect);

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
                return hash('xxh128', 'Inertia');
            });

            return Inertia::render('User/Edit');
        });

        $response = $this->withoutExceptionHandling()->get('/', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => 'f445bd0a2c393a5af14fc677f59980a9',
        ]);

        $response->assertSuccessful();
        $response->assertJson(['component' => 'User/Edit']);
    }

    public function test_the_url_can_be_resolved_with_a_custom_resolver(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            Inertia::resolveUrlUsing(function ($request, ResponseFactory $otherDependency) {
                $this->assertInstanceOf(HttpRequest::class, $request);
                $this->assertInstanceOf(ResponseFactory::class, $otherDependency);

                return '/my-custom-url';
            });

            return Inertia::render('User/Edit');
        });

        $response = $this->withoutExceptionHandling()->get('/', [
            'X-Inertia' => 'true',
        ]);

        $response->assertSuccessful();
        $response->assertJson([
            'component' => 'User/Edit',
            'url' => '/my-custom-url',
        ]);
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

    public function test_dot_props_are_merged_from_shared(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            Inertia::share('auth.user', [
                'name' => 'Jonathan',
            ]);

            return Inertia::render('User/Edit', [
                'auth.user.can.create_group' => false,
            ]);
        });

        $response = $this->withoutExceptionHandling()->get('/', ['X-Inertia' => 'true']);

        $response->assertSuccessful();
        $response->assertJson([
            'component' => 'User/Edit',
            'props' => [
                'auth' => [
                    'user' => [
                        'name' => 'Jonathan',
                        'can' => [
                            'create_group' => false,
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function test_shared_data_can_resolve_closure_arguments(): void
    {
        Inertia::share('query', fn (HttpRequest $request) => $request->query());

        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            return Inertia::render('User/Edit');
        });

        $response = $this->withoutExceptionHandling()->get('/?foo=bar', ['X-Inertia' => 'true']);

        $response->assertSuccessful();
        $response->assertJson([
            'component' => 'User/Edit',
            'props' => [
                'query' => [
                    'foo' => 'bar',
                ],
            ],
        ]);
    }

    public function test_dot_props_with_callbacks_are_merged_from_shared(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            Inertia::share('auth.user', fn () => [
                'name' => 'Jonathan',
            ]);

            return Inertia::render('User/Edit', [
                'auth.user.can.create_group' => false,
            ]);
        });

        $response = $this->withoutExceptionHandling()->get('/', ['X-Inertia' => 'true']);

        $response->assertSuccessful();
        $response->assertJson([
            'component' => 'User/Edit',
            'props' => [
                'auth' => [
                    'user' => [
                        'name' => 'Jonathan',
                        'can' => [
                            'create_group' => false,
                        ],
                    ],
                ],
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
        $factory = new ResponseFactory;
        $lazyProp = $factory->lazy(function () {
            return 'A lazy value';
        });

        $this->assertInstanceOf(LazyProp::class, $lazyProp);
    }

    public function test_can_create_deferred_prop(): void
    {
        $factory = new ResponseFactory;
        $deferredProp = $factory->defer(function () {
            return 'A deferred value';
        });

        $this->assertInstanceOf(DeferProp::class, $deferredProp);
        $this->assertSame($deferredProp->group(), 'default');
    }

    public function test_can_create_deferred_prop_with_custom_group(): void
    {
        $factory = new ResponseFactory;
        $deferredProp = $factory->defer(function () {
            return 'A deferred value';
        }, 'foo');

        $this->assertInstanceOf(DeferProp::class, $deferredProp);
        $this->assertSame($deferredProp->group(), 'foo');
    }

    public function test_can_create_merged_prop(): void
    {
        $factory = new ResponseFactory;
        $mergedProp = $factory->merge(function () {
            return 'A merged value';
        });

        $this->assertInstanceOf(MergeProp::class, $mergedProp);
    }

    public function test_can_create_deep_merged_prop(): void
    {
        $factory = new ResponseFactory;
        $mergedProp = $factory->deepMerge(function () {
            return 'A merged value';
        });

        $this->assertInstanceOf(MergeProp::class, $mergedProp);
    }

    public function test_can_create_deferred_and_merged_prop(): void
    {
        $factory = new ResponseFactory;
        $deferredProp = $factory->defer(function () {
            return 'A deferred + merged value';
        })->merge();

        $this->assertInstanceOf(DeferProp::class, $deferredProp);
    }

    public function test_can_create_deferred_and_deep_merged_prop(): void
    {
        $factory = new ResponseFactory;
        $deferredProp = $factory->defer(function () {
            return 'A deferred + merged value';
        })->deepMerge();

        $this->assertInstanceOf(DeferProp::class, $deferredProp);
    }

    public function test_can_create_optional_prop(): void
    {
        $factory = new ResponseFactory;
        $optionalProp = $factory->optional(function () {
            return 'An optional value';
        });

        $this->assertInstanceOf(OptionalProp::class, $optionalProp);
    }

    public function test_can_create_scroll_prop(): void
    {
        $factory = new ResponseFactory;
        $data = ['item1', 'item2'];

        $scrollProp = $factory->scroll($data);

        $this->assertInstanceOf(ScrollProp::class, $scrollProp);
        $this->assertSame($data, $scrollProp());
    }

    public function test_can_create_scroll_prop_with_metadata_provider(): void
    {
        $factory = new ResponseFactory;
        $data = ['item1', 'item2'];
        $metadataProvider = new ScrollMetadata('custom', 1, 3, 2);

        $scrollProp = $factory->scroll($data, 'data', $metadataProvider);

        $this->assertInstanceOf(ScrollProp::class, $scrollProp);
        $this->assertSame($data, $scrollProp());
        $this->assertEquals([
            'pageName' => 'custom',
            'previousPage' => 1,
            'nextPage' => 3,
            'currentPage' => 2,
        ], $scrollProp->metadata());
    }

    public function test_can_create_once_prop(): void
    {
        $factory = new ResponseFactory;
        $onceProp = $factory->once(function () {
            return 'A once value';
        });

        $this->assertInstanceOf(OnceProp::class, $onceProp);
    }

    public function test_can_create_deferred_and_once_prop(): void
    {
        $factory = new ResponseFactory;
        $deferredProp = $factory->defer(function () {
            return 'A deferred + once value';
        })->once();

        $this->assertInstanceOf(DeferProp::class, $deferredProp);
        $this->assertTrue($deferredProp->shouldResolveOnce());
    }

    public function test_can_create_always_prop(): void
    {
        $factory = new ResponseFactory;
        $alwaysProp = $factory->always(function () {
            return 'An always value';
        });

        $this->assertInstanceOf(AlwaysProp::class, $alwaysProp);
    }

    public function test_will_accept_arrayabe_props(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            Inertia::share('foo', 'bar');

            return Inertia::render('User/Edit', new class implements Arrayable
            {
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

    public function test_will_accept_instances_of_provides_inertia_props(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            return Inertia::render('User/Edit', new ExampleInertiaPropsProvider([
                'foo' => 'bar',
            ]));
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

    public function test_will_accept_arrays_containing_provides_inertia_props_in_render(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            return Inertia::render('User/Edit', [
                'regular' => 'prop',
                new ExampleInertiaPropsProvider([
                    'from_object' => 'value',
                ]),
                'another' => 'normal_prop',
            ]);
        });

        $response = $this->withoutExceptionHandling()->get('/', ['X-Inertia' => 'true']);
        $response->assertSuccessful();
        $response->assertJson([
            'component' => 'User/Edit',
            'props' => [
                'regular' => 'prop',
                'from_object' => 'value',
                'another' => 'normal_prop',
            ],
        ]);
    }

    public function test_can_share_instances_of_provides_inertia_props(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            Inertia::share(new ExampleInertiaPropsProvider([
                'shared' => 'data',
            ]));

            return Inertia::render('User/Edit', [
                'regular' => 'prop',
            ]);
        });

        $response = $this->withoutExceptionHandling()->get('/', ['X-Inertia' => 'true']);
        $response->assertSuccessful();
        $response->assertJson([
            'component' => 'User/Edit',
            'props' => [
                'shared' => 'data',
                'regular' => 'prop',
            ],
        ]);
    }

    public function test_can_share_arrays_containing_provides_inertia_props(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            Inertia::share([
                'regular' => 'shared_prop',
                new ExampleInertiaPropsProvider([
                    'from_object' => 'shared_value',
                ]),
            ]);

            return Inertia::render('User/Edit', [
                'component' => 'prop',
            ]);
        });

        $response = $this->withoutExceptionHandling()->get('/', ['X-Inertia' => 'true']);
        $response->assertSuccessful();
        $response->assertJson([
            'component' => 'User/Edit',
            'props' => [
                'regular' => 'shared_prop',
                'from_object' => 'shared_value',
                'component' => 'prop',
            ],
        ]);
    }

    public function test_will_throw_exception_if_component_does_not_exist_when_ensuring_is_enabled(): void
    {
        config()->set('inertia.ensure_pages_exist', true);

        $this->expectException(ComponentNotFoundException::class);
        $this->expectExceptionMessage('Inertia page component [foo] not found.');

        (new ResponseFactory)->render('foo');
    }

    public function test_will_not_throw_exception_if_component_does_not_exist_when_ensuring_is_disabled(): void
    {
        config()->set('inertia.ensure_pages_exist', false);

        $response = (new ResponseFactory)->render('foo');
        $this->assertInstanceOf(\Inertia\Response::class, $response);
    }

    public function test_share_once_shares_a_once_prop(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            Inertia::shareOnce('settings', fn () => ['theme' => 'dark']);

            return Inertia::render('User/Edit');
        });

        $response = $this->withoutExceptionHandling()->get('/', ['X-Inertia' => 'true']);

        $response->assertSuccessful();
        $response->assertJson([
            'component' => 'User/Edit',
            'props' => [
                'settings' => ['theme' => 'dark'],
            ],
            'onceProps' => [
                'settings' => [
                    'prop' => 'settings',
                    'expiresAt' => null,
                ],
            ],
        ]);
    }

    public function test_share_once_is_chainable(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            $prop = Inertia::shareOnce('settings', fn () => ['theme' => 'dark'])
                ->as('app-settings')
                ->until(60);

            $this->assertInstanceOf(OnceProp::class, $prop);

            return Inertia::render('User/Edit');
        });

        $response = $this->withoutExceptionHandling()->get('/', ['X-Inertia' => 'true']);

        $response->assertSuccessful();
        $data = $response->json();

        $this->assertArrayHasKey('onceProps', $data);
        $this->assertArrayHasKey('app-settings', $data['onceProps']);
        $this->assertEquals('settings', $data['onceProps']['app-settings']['prop']);
        $this->assertNotNull($data['onceProps']['app-settings']['expiresAt']);
    }

    public function test_forcefully_refreshing_a_once_prop_includes_it_in_once_props(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            return Inertia::render('User/Edit', [
                'settings' => Inertia::once(fn () => ['theme' => 'dark'])->fresh(),
            ]);
        });

        $response = $this->withoutExceptionHandling()->get('/', ['X-Inertia' => 'true']);

        $response->assertSuccessful();
        $response->assertJson([
            'component' => 'User/Edit',
            'props' => [
                'settings' => ['theme' => 'dark'],
            ],
            'onceProps' => [
                'settings' => ['prop' => 'settings', 'expiresAt' => null],
            ],
        ]);
    }

    public function test_once_prop_is_included_in_once_props_by_default(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            return Inertia::render('User/Edit', [
                'settings' => Inertia::once(fn () => ['theme' => 'dark']),
            ]);
        });

        $response = $this->withoutExceptionHandling()->get('/', ['X-Inertia' => 'true']);

        $response->assertSuccessful();
        $response->assertJson([
            'component' => 'User/Edit',
            'props' => [
                'settings' => ['theme' => 'dark'],
            ],
            'onceProps' => [
                'settings' => [
                    'prop' => 'settings',
                    'expiresAt' => null,
                ],
            ],
        ]);
    }

    public function test_flash_data_is_flashed_to_session_on_redirect(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->post('/flash-test', function () {
            return Inertia::flash(['message' => 'Success!'])->back();
        });

        $response = $this->post('/flash-test', [], [
            'X-Inertia' => 'true',
        ]);

        $response->assertRedirect();
        $this->assertEquals(['message' => 'Success!'], session('inertia.flash_data'));
    }

    public function test_render_with_flash_includes_flash_in_page(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->post('/flash-test', function () {
            return Inertia::flash('type', 'success')
                ->render('User/Edit', ['user' => 'Jonathan'])
                ->flash(['message' => 'User updated!']);
        });

        $response = $this->post('/flash-test', [], [
            'X-Inertia' => 'true',
        ]);

        $response->assertSuccessful();
        $response->assertJson([
            'component' => 'User/Edit',
            'props' => [
                'user' => 'Jonathan',
            ],
            'flash' => [
                'message' => 'User updated!',
                'type' => 'success',
            ],
        ]);

        // Flash data should not persist in session after being included in response
        $this->assertNull(session('inertia.flash_data'));
    }

    public function test_render_without_flash_does_not_include_flash_key(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/no-flash', function () {
            return Inertia::render('User/Edit', ['user' => 'Jonathan']);
        });

        $response = $this->get('/no-flash', [
            'X-Inertia' => 'true',
        ]);

        $response->assertSuccessful();
        $response->assertJson([
            'component' => 'User/Edit',
        ]);
        $response->assertJsonMissing(['flash']);
    }

    public function test_multiple_flash_calls_are_merged(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->post('/create', function () {
            Inertia::flash('foo', 'value1');
            Inertia::flash('bar', 'value2');

            return Inertia::render('User/Show');
        });

        $response = $this->post('/create', [], ['X-Inertia' => 'true']);

        $response->assertJson([
            'flash' => [
                'foo' => 'value1',
                'bar' => 'value2',
            ],
        ]);
    }
}
