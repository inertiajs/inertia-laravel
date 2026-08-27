<?php

namespace Inertia\Tests\Testing;

use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Middleware;
use Inertia\Testing\AssertableInertia;
use Inertia\Tests\Stubs\ExampleMiddleware;
use Inertia\Tests\TestCase;
use PHPUnit\Framework\AssertionFailedError;

class AssertableInertiaTest extends TestCase
{
    public function test_the_view_is_served_by_inertia(): void
    {
        $response = $this->makeMockRequest(
            Inertia::render('foo')
        );

        $response->assertInertia();
    }

    public function test_the_view_is_not_served_by_inertia(): void
    {
        $response = $this->makeMockRequest(view('welcome'));
        $response->assertOk(); // Make sure we can render the built-in Orchestra 'welcome' view..

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Not a valid Inertia response.');

        $response->assertInertia();
    }

    public function test_the_component_matches(): void
    {
        $response = $this->makeMockRequest(
            Inertia::render('foo')
        );

        $response->assertInertia(function ($inertia) {
            $inertia->component('foo');
        });
    }

    public function test_the_component_does_not_match(): void
    {
        $response = $this->makeMockRequest(
            Inertia::render('foo')
        );

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Unexpected Inertia page component.');

        $response->assertInertia(function ($inertia) {
            $inertia->component('bar');
        });
    }

    public function test_the_component_exists_on_the_filesystem(): void
    {
        $response = $this->makeMockRequest(
            Inertia::render('Stubs/ExamplePage')
        );

        config()->set('inertia.testing.ensure_pages_exist', true);
        $response->assertInertia(function ($inertia) {
            $inertia->component('Stubs/ExamplePage');
        });
    }

    public function test_the_component_exists_on_the_filesystem_when_a_component_resolver_is_configured(): void
    {
        $calledWith = null;

        Inertia::transformComponentUsing(static function (string $name) use (&$calledWith): string {
            $calledWith = $name;

            return "{$name}/Page";
        });

        $response = $this->makeMockRequest(
            Inertia::render('Stubs/Example')
        );

        config()->set('inertia.testing.ensure_pages_exist', true);

        $response->assertInertia(function ($inertia) {
            $inertia->component('Stubs/Example/Page');
        });

        $this->assertSame('Stubs/Example', $calledWith);
    }

    public function test_the_component_does_not_exist_on_the_filesystem(): void
    {
        $response = $this->makeMockRequest(
            Inertia::render('foo')
        );

        config()->set('inertia.testing.ensure_pages_exist', true);
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Inertia page component file [foo] does not exist.');

        $response->assertInertia(function ($inertia) {
            $inertia->component('foo');
        });
    }

    public function test_it_can_force_enable_the_component_file_existence(): void
    {
        $response = $this->makeMockRequest(
            Inertia::render('foo')
        );

        config()->set('inertia.testing.ensure_pages_exist', false);
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Inertia page component file [foo] does not exist.');

        $response->assertInertia(function ($inertia) {
            $inertia->component('foo', true);
        });
    }

    public function test_it_can_force_disable_the_component_file_existence_check(): void
    {
        $response = $this->makeMockRequest(
            Inertia::render('foo')
        );

        config()->set('inertia.testing.ensure_pages_exist', true);

        $response->assertInertia(function ($inertia) {
            $inertia->component('foo', false);
        });
    }

    public function test_the_component_does_not_exist_on_the_filesystem_when_it_does_not_exist_relative_to_any_of_the_given_paths(): void
    {
        $response = $this->makeMockRequest(
            Inertia::render('fixtures/ExamplePage')
        );

        config()->set('inertia.testing.ensure_pages_exist', true);
        config()->set('inertia.pages.paths', [realpath(__DIR__)]);
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Inertia page component file [fixtures/ExamplePage] does not exist.');

        $response->assertInertia(function ($inertia) {
            $inertia->component('fixtures/ExamplePage');
        });
    }

    public function test_the_component_does_not_exist_on_the_filesystem_when_it_does_not_have_one_of_the_configured_extensions(): void
    {
        $response = $this->makeMockRequest(
            Inertia::render('fixtures/ExamplePage')
        );

        config()->set('inertia.testing.ensure_pages_exist', true);
        config()->set('inertia.pages.extensions', ['bin', 'exe', 'svg']);
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Inertia page component file [fixtures/ExamplePage] does not exist.');

        $response->assertInertia(function ($inertia) {
            $inertia->component('fixtures/ExamplePage');
        });
    }

    public function test_the_page_url_matches(): void
    {
        $response = $this->makeMockRequest(
            Inertia::render('foo')
        );

        $response->assertInertia(function ($inertia) {
            $inertia->url('/example-url');
        });
    }

    public function test_the_page_url_does_not_match(): void
    {
        $response = $this->makeMockRequest(
            Inertia::render('foo')
        );

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Unexpected Inertia page url.');

        $response->assertInertia(function ($inertia) {
            $inertia->url('/invalid-page');
        });
    }

    public function test_the_asset_version_matches(): void
    {
        Inertia::version('example-version');

        $response = $this->makeMockRequest(
            Inertia::render('foo')
        );

        $response->assertInertia(function ($inertia) {
            $inertia->version('example-version');
        });
    }

    public function test_the_asset_version_does_not_match(): void
    {
        Inertia::version('example-version');

        $response = $this->makeMockRequest(
            Inertia::render('foo')
        );

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Unexpected Inertia asset version.');

        $response->assertInertia(function ($inertia) {
            $inertia->version('different-version');
        });
    }

    public function test_reloading_a_visit(): void
    {
        $foo = 0;

        $response = $this->makeMockRequest(function () use (&$foo) {
            return Inertia::render('foo', [
                'foo' => $foo++,
            ]);
        });

        $called = false;

        $response->assertInertia(function ($inertia) use (&$called) {
            $inertia->where('foo', 0);

            $inertia->reload(function ($inertia) use (&$called) {
                $inertia->where('foo', 1);
                $called = true;
            });
        });

        $this->assertTrue($called);
    }

    public function test_optional_props_can_be_evaluated(): void
    {
        $response = $this->makeMockRequest(
            Inertia::render('foo', [
                'foo' => 'bar',
                'optional1' => Inertia::optional(fn () => 'baz'),
                'optional2' => Inertia::optional(fn () => 'qux'),
            ])
        );

        $called = false;

        $response->assertInertia(function ($inertia) use (&$called) {
            $inertia->where('foo', 'bar');
            $inertia->missing('optional1');
            $inertia->missing('optional2');

            $result = $inertia->reloadOnly('optional1', function ($inertia) use (&$called) {
                $inertia->missing('foo');
                $inertia->where('optional1', 'baz');
                $inertia->missing('optional2');
                $called = true;
            });

            $this->assertSame($result, $inertia);
        });

        $this->assertTrue($called);
    }

    public function test_optional_props_can_be_evaluated_with_except(): void
    {
        $response = $this->makeMockRequest(
            Inertia::render('foo', [
                'foo' => 'bar',
                'lazy1' => Inertia::optional(fn () => 'baz'),
                'lazy2' => Inertia::optional(fn () => 'qux'),
            ])
        );

        $called = false;

        $response->assertInertia(function ($inertia) use (&$called) {
            $inertia->where('foo', 'bar');
            $inertia->missing('lazy1');
            $inertia->missing('lazy2');

            $result = $inertia->reloadOnly(['lazy1'], function ($inertia) use (&$called) {
                $inertia->missing('foo');
                $inertia->where('lazy1', 'baz');
                $inertia->missing('lazy2');
                $called = true;
            });

            $this->assertSame($result, $inertia);
        });

        $this->assertTrue($called);
    }

    public function test_lazy_props_can_be_evaluated_with_except(): void
    {
        $response = $this->makeMockRequest(
            Inertia::render('foo', [
                'foo' => 'bar',
                'optional1' => Inertia::optional(fn () => 'baz'),
                'optional2' => Inertia::optional(fn () => 'qux'),
            ])
        );

        $called = false;

        $response->assertInertia(function (AssertableInertia $inertia) use (&$called) {
            $inertia->where('foo', 'bar');
            $inertia->missing('optional1');
            $inertia->missing('optional2');

            $inertia->reloadExcept('optional1', function ($inertia) use (&$called) {
                $inertia->where('foo', 'bar');
                $inertia->missing('optional1');
                $inertia->where('optional2', 'qux');
                $called = true;
            });
        });

        $this->assertTrue($called);
    }

    public function test_lazy_props_can_be_evaluated_with_except_when_except_is_array(): void
    {
        $response = $this->makeMockRequest(
            Inertia::render('foo', [
                'foo' => 'bar',
                'lazy1' => Inertia::optional(fn () => 'baz'),
                'lazy2' => Inertia::optional(fn () => 'qux'),
            ])
        );

        $called = false;

        $response->assertInertia(function ($inertia) use (&$called) {
            $inertia->where('foo', 'bar');
            $inertia->missing('lazy1');
            $inertia->missing('lazy2');

            $inertia->reloadExcept(['lazy1'], function ($inertia) use (&$called) {
                $inertia->where('foo', 'bar');
                $inertia->missing('lazy1');
                $inertia->where('lazy2', 'qux');
                $called = true;
            });
        });

        $this->assertTrue($called);
    }

    public function test_assert_against_deferred_props(): void
    {
        $response = $this->makeMockRequest(
            Inertia::render('foo', [
                'foo' => 'bar',
                'deferred1' => Inertia::defer(fn () => 'baz'),
                'deferred2' => Inertia::defer(fn () => 'qux', 'custom'),
                'deferred3' => Inertia::defer(fn () => 'quux', 'custom'),
            ])
        );

        $called = 0;

        $response->assertInertia(function (AssertableInertia $inertia) use (&$called) {
            $inertia->where('foo', 'bar');
            $inertia->missing('deferred1');
            $inertia->missing('deferred2');
            $inertia->missing('deferred3');

            $inertia->loadDeferredProps(function (AssertableInertia $inertia) use (&$called) {
                $inertia->where('deferred1', 'baz');
                $inertia->where('deferred2', 'qux');
                $inertia->where('deferred3', 'quux');
                $called++;
            });

            $inertia->loadDeferredProps('default', function (AssertableInertia $inertia) use (&$called) {
                $inertia->where('deferred1', 'baz');
                $inertia->missing('deferred2');
                $inertia->missing('deferred3');
                $called++;
            });

            $inertia->loadDeferredProps('custom', function (AssertableInertia $inertia) use (&$called) {
                $inertia->missing('deferred1');
                $inertia->where('deferred2', 'qux');
                $inertia->where('deferred3', 'quux');
                $called++;
            });

            $inertia->loadDeferredProps(['default', 'custom'], function (AssertableInertia $inertia) use (&$called) {
                $inertia->where('deferred1', 'baz');
                $inertia->where('deferred2', 'qux');
                $inertia->where('deferred3', 'quux');
                $called++;
            });
        });

        $this->assertSame(4, $called);
    }

    public function test_the_flash_data_can_be_asserted(): void
    {
        $response = $this->makeMockRequest(
            fn () => Inertia::render('foo')->flash([
                'message' => 'Hello World',
                'notification' => ['type' => 'success'],
            ]),
            StartSession::class
        );

        $response->assertInertia(function (AssertableInertia $inertia) {
            $inertia->hasFlash('message');
            $inertia->hasFlash('message', 'Hello World');
            $inertia->hasFlash('notification.type', 'success');
            $inertia->missingFlash('other');
            $inertia->missingFlash('notification.other');
        });
    }

    public function test_the_flash_assertion_fails_when_key_is_missing(): void
    {
        $response = $this->makeMockRequest(Inertia::render('foo'));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Inertia Flash Data is missing key [message].');

        $response->assertInertia(fn (AssertableInertia $inertia) => $inertia->hasFlash('message'));
    }

    public function test_the_flash_assertion_fails_when_value_does_not_match(): void
    {
        $response = $this->makeMockRequest(
            fn () => Inertia::render('foo')->flash('message', 'Hello World'),
            StartSession::class
        );

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Inertia Flash Data [message] does not match expected value.');

        $response->assertInertia(fn (AssertableInertia $inertia) => $inertia->hasFlash('message', 'Different'));
    }

    public function test_the_missing_flash_assertion_fails_when_key_exists(): void
    {
        $response = $this->makeMockRequest(
            fn () => Inertia::render('foo')->flash('message', 'Hello World'),
            StartSession::class
        );

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Inertia Flash Data has unexpected key [message].');

        $response->assertInertia(fn (AssertableInertia $inertia) => $inertia->missingFlash('message'));
    }

    public function test_the_flash_data_is_available_after_redirect(): void
    {
        $middleware = [StartSession::class, Middleware::class];

        Route::middleware($middleware)->get('/action', function () {
            Inertia::flash('message', 'Success!');

            return redirect('/dashboard');
        });

        Route::middleware($middleware)->get('/dashboard', function () {
            return Inertia::render('Dashboard');
        });

        $this->get('/action')->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertInertia(fn (AssertableInertia $inertia) => $inertia->hasFlash('message', 'Success!'));
    }

    public function test_the_flash_data_is_available_after_double_redirect(): void
    {
        $middleware = [StartSession::class, Middleware::class];

        Route::middleware($middleware)->get('/action', function () {
            Inertia::flash('message', 'Success!');

            return redirect('/intermediate');
        });

        Route::middleware($middleware)->get('/intermediate', function () {
            return redirect('/dashboard');
        });

        Route::middleware($middleware)->get('/dashboard', function () {
            return Inertia::render('Dashboard');
        });

        $this->get('/action')->assertRedirect('/intermediate');
        $this->get('/intermediate')->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertInertia(fn (AssertableInertia $inertia) => $inertia->hasFlash('message', 'Success!'));
    }

    public function test_an_inertia_json_response_can_be_asserted(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/users', function () {
            return Inertia::render('Users/Index', ['users' => []]);
        });

        // A page answered to an Inertia request is JSON rather than a view, and asserting on it
        // reads the body: a close response is only reachable that way, and an ordinary one is too.
        $this->get('/users', ['X-Inertia' => 'true'])
            ->assertInertia(fn (AssertableInertia $inertia) => $inertia->component('Users/Index')->has('users'));
    }

    public function test_a_layer_response_can_be_asserted(): void
    {
        $response = $this->makeMockRequest(
            Inertia::render('Users/Edit', ['user' => ['id' => 5]])->layer(base: '/users', key: 'Users/Edit')
        );

        $response->assertInertia(function (AssertableInertia $inertia) {
            $inertia->component('Users/Edit');
            $inertia->where('user.id', 5);
            $inertia->hasLayer('Users/Edit');
            $inertia->layerBase('/users');
        });
    }

    public function test_the_layer_key_defaults_to_the_component_name(): void
    {
        $response = $this->makeMockRequest(
            Inertia::render('Users/Edit')->layer(base: '/users')
        );

        // The mark carries no key, so the assertion resolves it the way the client does.
        $response->assertInertia(fn (AssertableInertia $inertia) => $inertia->hasLayer('Users/Edit'));
    }

    public function test_an_explicitly_empty_layer_key_resolves_to_the_component_name(): void
    {
        $response = $this->makeMockRequest(
            Inertia::render('Users/Edit')->layer(key: '')
        );

        // The client reads the key with `||`, so an empty one falls back to the component.
        $response->assertInertia(fn (AssertableInertia $inertia) => $inertia->hasLayer('Users/Edit'));
    }

    public function test_a_layer_without_a_base_carries_the_key_only(): void
    {
        $response = $this->makeMockRequest(
            Inertia::render('Users/Edit')->layer(key: 'Users/Edit')
        );

        $response->assertInertia(fn (AssertableInertia $inertia) => $inertia->hasLayer('Users/Edit'));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Inertia layer has no base.');

        $response->assertInertia(fn (AssertableInertia $inertia) => $inertia->layerBase('/users'));
    }

    public function test_the_layer_assertion_fails_when_the_page_is_not_a_layer(): void
    {
        $response = $this->makeMockRequest(Inertia::render('Users/Edit'));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Inertia page is not a layer.');

        $response->assertInertia(fn (AssertableInertia $inertia) => $inertia->hasLayer());
    }

    public function test_a_layer_naming_neither_a_key_nor_a_base_is_still_a_layer(): void
    {
        $response = $this->makeMockRequest(Inertia::render('Users/Edit')->layer());

        $response->assertInertia(function (AssertableInertia $inertia) {
            $inertia->hasLayer();
            $inertia->hasLayer('Users/Edit');
        });
    }

    public function test_the_layer_object_is_reachable_on_the_page(): void
    {
        $response = $this->makeMockRequest(
            Inertia::render('Users/Edit')->layer(base: '/users', key: 'Users/Edit')
        );

        $page = $response->inertiaPage();

        $this->assertSame('Users/Edit', $page['component']);
        $this->assertSame(['key' => 'Users/Edit', 'base' => '/users'], $page['layer']);
        $this->assertArrayNotHasKey('close', $page);
    }

    public function test_a_close_response_can_be_asserted(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->post('/close', function () {
            return Inertia::close();
        });

        $this->post('/close', [], ['X-Inertia' => 'true'])
            ->assertInertia(fn (AssertableInertia $inertia) => $inertia->isClose());
    }

    public function test_a_close_response_carries_the_close_key_on_the_page(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->post('/close', function () {
            return Inertia::close();
        });

        $page = $this->post('/close', [], ['X-Inertia' => 'true'])->inertiaPage();

        $this->assertSame('', $page['component']);
        $this->assertTrue($page['close']);
        $this->assertArrayNotHasKey('layer', $page);
    }

    public function test_the_close_assertion_fails_when_the_page_is_not_a_close_response(): void
    {
        $response = $this->makeMockRequest(Inertia::render('Users/Edit'));

        $this->expectException(AssertionFailedError::class);
        $response->assertInertia(fn (AssertableInertia $inertia) => $inertia->isClose());
    }
}
