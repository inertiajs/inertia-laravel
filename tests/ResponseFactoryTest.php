<?php

namespace Inertia\Tests;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\NullSessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;
use Inertia\AlwaysProp;
use Inertia\Exceptions\StrictPropertiesException;
use Inertia\Inertia;
use Inertia\LazyProp;
use Inertia\ResponseFactory;
use Inertia\Tests\Stubs\ExampleMiddleware;

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

            return Inertia::render('User/Edit', new class() implements Arrayable
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

    public function test_strict_models_mode_checks_if_models_have_serialization_rules()
    {
        $this->expectException(StrictPropertiesException::class);
        $this->expectExceptionMessage('Prop "user" is shared without serialization rules.');

        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            Inertia::strictModels();

            return Inertia::render('User/Edit', [
                'user' => new class(['name' => 'John', 'email' => 'john@doe.com']) extends User {
                    protected $fillable = [
                        'name',
                    ];
                },
            ]);
        });

        $this->withoutExceptionHandling()->get('/', ['X-Inertia' => 'true']);
    }

    public function test_strict_models_mode_allows_models_with_hidden_serialization_rules()
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            Inertia::strictModels();

            return Inertia::render('User/Edit', [
                'user' => new class(['name' => 'John', 'email' => 'john@doe.com']) extends User {
                    protected $fillable = [
                        'name',
                        'email',
                    ];

                    protected $hidden = ['email'];
                },
            ]);
        });

        $response = $this->withoutExceptionHandling()->get('/', ['X-Inertia' => 'true']);
        $response->assertSuccessful();
        $response->assertJson([
            'component' => 'User/Edit',
            'props' => [
                'user' => [
                    'name' => 'John',
                ],
            ],
        ]);
    }

    public function test_strict_models_mode_allows_models_with_visible_serialization_rules()
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            Inertia::strictModels();

            return Inertia::render('User/Edit', [
                'user' => new class(['name' => 'John', 'email' => 'john@doe.com']) extends User {
                    protected $fillable = [
                        'name',
                        'email',
                    ];

                    protected $visible = ['name'];
                },
            ]);
        });

        $response = $this->withoutExceptionHandling()->get('/', ['X-Inertia' => 'true']);
        $response->assertSuccessful();
        $response->assertJson([
            'component' => 'User/Edit',
            'props' => [
                'user' => [
                    'name' => 'John',
                ],
            ],
        ]);
    }

    public function test_strict_models_allows_wrapping_in_json_resources()
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            Inertia::strictModels();

            $user = new class(['name' => 'John', 'email' => 'john@doe.com']) extends User {
                protected $fillable = [
                    'name',
                    'email',
                ];
            };

            return Inertia::render('User/Edit', [
                'user' => new class($user) extends JsonResource {
                    public static $wrap = null;

                    public function toArray($request)
                    {
                        return [
                            'name' => $this->name,
                        ];
                    }
                },
            ]);
        });

        $response = $this->withoutExceptionHandling()->get('/', ['X-Inertia' => 'true']);
        $response->assertSuccessful();
        $response->assertJson([
            'component' => 'User/Edit',
            'props' => [
                'user' => [
                    'name' => 'John',
                ],
            ],
        ]);
    }
}
