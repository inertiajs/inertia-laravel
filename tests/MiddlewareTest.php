<?php

namespace Inertia\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RouteInstance;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Inertia\AlwaysProp;
use Inertia\Inertia;
use Inertia\Middleware;
use Inertia\Tests\Stubs\CustomUrlResolverMiddleware;
use Inertia\Tests\Stubs\ExampleMiddleware;
use Inertia\Tests\Stubs\WithAllErrorsMiddleware;
use LogicException;
use PHPUnit\Framework\Attributes\After;

class MiddlewareTest extends TestCase
{
    #[After]
    public function cleanupPublicFolder(): void
    {
        (new Filesystem)->cleanDirectory(public_path());
    }

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

    public function test_the_url_can_be_resolved_with_a_custom_resolver(): void
    {
        $this->prepareMockEndpoint(middleware: new CustomUrlResolverMiddleware);

        $response = $this->withoutExceptionHandling()->get('/', [
            'X-Inertia' => 'true',
        ]);

        $response->assertSuccessful();
        $response->assertJson([
            'component' => 'User/Edit',
            'url' => '/my-custom-url',
        ]);
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

    public function test_validation_errors_are_mapped_to_strings_by_default(): void
    {
        Session::put('errors', (new ViewErrorBag)->put('default', new MessageBag([
            'name' => ['The name field is required.'],
            'email' => ['Not a valid email address.', 'Another email error.'],
        ])));

        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            $errors = Inertia::getShared('errors')();

            $this->assertIsObject($errors);
            $this->assertSame('The name field is required.', $errors->name);
            $this->assertSame('Not a valid email address.', $errors->email);
        });

        $this->withoutExceptionHandling()->get('/');
    }

    public function test_validation_errors_can_remain_multiple_per_field(): void
    {
        Session::put('errors', (new ViewErrorBag)->put('default', new MessageBag([
            'name' => ['The name field is required.'],
            'email' => ['Not a valid email address.', 'Another email error.'],
        ])));

        Route::middleware([StartSession::class, WithAllErrorsMiddleware::class])->get('/', function () {
            $errors = Inertia::getShared('errors')();

            $this->assertIsObject($errors);
            $this->assertSame(['The name field is required.'], $errors->name);
            $this->assertSame(
                ['Not a valid email address.', 'Another email error.'],
                $errors->email
            );
        });

        $this->withoutExceptionHandling()->get('/');
    }

    public function test_validation_errors_with_named_error_bags_are_scoped(): void
    {
        Session::put('errors', (new ViewErrorBag)->put('example', new MessageBag([
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
        Session::put('errors', (new ViewErrorBag)->put('example', new MessageBag([
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
        Session::put('errors', (new ViewErrorBag)->put('default', new MessageBag([
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
        $this->prepareMockEndpoint(null, [], new class extends Middleware
        {
            protected $rootView = 'welcome';
        });

        $response = $this->get('/');
        $response->assertOk();
        $response->assertViewIs('welcome');
    }

    public function test_middleware_can_change_the_root_view_by_overriding_the_rootview_method(): void
    {
        $this->prepareMockEndpoint(null, [], new class extends Middleware
        {
            public function rootView(Request $request): string
            {
                return 'welcome';
            }
        });

        $response = $this->get('/');
        $response->assertOk();
        $response->assertViewIs('welcome');
    }

    public function test_determine_the_version_by_a_hash_of_the_asset_url(): void
    {
        config(['app.asset_url' => $url = 'https://example.com/assets']);

        $this->prepareMockEndpoint(middleware: new Middleware);

        $response = $this->get('/');
        $response->assertOk();
        $response->assertViewHas('page.version', hash('xxh128', $url));
    }

    public function test_determine_the_version_by_a_hash_of_the_vite_manifest(): void
    {
        $filesystem = new Filesystem;
        $filesystem->ensureDirectoryExists(public_path('build'));
        $filesystem->put(
            public_path('build/manifest.json'),
            $contents = json_encode(['vite' => true])
        );

        $this->prepareMockEndpoint(middleware: new Middleware);

        $response = $this->get('/');
        $response->assertOk();
        $response->assertViewHas('page.version', hash('xxh128', $contents));
    }

    public function test_determine_the_version_by_a_hash_of_the_mix_manifest(): void
    {
        $filesystem = new Filesystem;
        $filesystem->ensureDirectoryExists(public_path());
        $filesystem->put(
            public_path('mix-manifest.json'),
            $contents = json_encode(['mix' => true])
        );

        $this->prepareMockEndpoint(middleware: new Middleware);

        $response = $this->get('/');
        $response->assertOk();
        $response->assertViewHas('page.version', hash('xxh128', $contents));
    }

    public function test_middleware_share_once(): void
    {
        $middleware = new class extends Middleware
        {
            public function shareOnce(Request $request): array
            {
                return [
                    'permissions' => fn () => ['admin' => true],
                    'settings' => Inertia::once(fn () => ['theme' => 'dark'])
                        ->as('app-settings')
                        ->until(60),
                ];
            }
        };

        Route::middleware(StartSession::class)->get('/', function (Request $request) use ($middleware) {
            return $middleware->handle($request, function ($request) {
                return Inertia::render('User/Edit')->toResponse($request);
            });
        });

        $response = $this->get('/', ['X-Inertia' => 'true']);

        $response->assertSuccessful();
        $response->assertJson([
            'props' => [
                'permissions' => ['admin' => true],
                'settings' => ['theme' => 'dark'],
            ],
            'onceProps' => [
                'permissions' => ['prop' => 'permissions', 'expiresAt' => null],
                'app-settings' => ['prop' => 'settings'],
            ],
        ]);
        $this->assertNotNull($response->json('onceProps.app-settings.expiresAt'));
    }

    public function test_middleware_share_and_share_once_are_merged(): void
    {
        $middleware = new class extends Middleware
        {
            public function share(Request $request): array
            {
                return array_merge(parent::share($request), [
                    'flash' => fn () => ['message' => 'Hello'],
                ]);
            }

            public function shareOnce(Request $request): array
            {
                return array_merge(parent::shareOnce($request), [
                    'permissions' => fn () => ['admin' => true],
                ]);
            }
        };

        Route::middleware(StartSession::class)->get('/', function (Request $request) use ($middleware) {
            return $middleware->handle($request, function ($request) {
                return Inertia::render('User/Edit')->toResponse($request);
            });
        });

        $response = $this->get('/', ['X-Inertia' => 'true']);

        $response->assertSuccessful();
        $response->assertJson([
            'props' => [
                'flash' => ['message' => 'Hello'],
                'permissions' => ['admin' => true],
            ],
            'onceProps' => [
                'permissions' => ['prop' => 'permissions', 'expiresAt' => null],
            ],
        ]);
    }

    public function test_flash_data_is_preserved_on_non_inertia_redirect(): void
    {
        Route::middleware([StartSession::class, Middleware::class])->get('/action', function () {
            Inertia::flash('message', 'Success!');

            return redirect('/dashboard');
        });

        Route::middleware([StartSession::class, Middleware::class])->get('/dashboard', function () {
            return Inertia::render('Dashboard');
        });

        $response = $this->get('/action');
        $response->assertRedirect('/dashboard');

        $response = $this->get('/dashboard', ['X-Inertia' => 'true']);
        $response->assertJson([
            'flash' => ['message' => 'Success!'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $shared
     */
    private function prepareMockEndpoint(int|string|null $version = null, array $shared = [], ?Middleware $middleware = null): RouteInstance
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
