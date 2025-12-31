<?php

namespace Inertia\Tests\Testing;

use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Middleware;
use Inertia\Testing\AssertableInertia;
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
        config()->set('inertia.testing.page_paths', [realpath(__DIR__)]);
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
        config()->set('inertia.testing.page_extensions', ['bin', 'exe', 'svg']);
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

    public function test_lazy_props_can_be_evaluated(): void
    {
        $response = $this->makeMockRequest(
            Inertia::render('foo', [
                'foo' => 'bar',
                'lazy1' => Inertia::lazy(fn () => 'baz'),
                'lazy2' => Inertia::lazy(fn () => 'qux'),
            ])
        );

        $called = false;

        $response->assertInertia(function ($inertia) use (&$called) {
            $inertia->where('foo', 'bar');
            $inertia->missing('lazy1');
            $inertia->missing('lazy2');

            $result = $inertia->reloadOnly('lazy1', function ($inertia) use (&$called) {
                $inertia->missing('foo');
                $inertia->where('lazy1', 'baz');
                $inertia->missing('lazy2');
                $called = true;
            });

            $this->assertSame($result, $inertia);
        });

        $this->assertTrue($called);
    }

    public function test_lazy_props_can_be_evaluated_when_only_is_array(): void
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
                'lazy1' => Inertia::lazy(fn () => 'baz'),
                'lazy2' => Inertia::lazy(fn () => 'qux'),
            ])
        );

        $called = false;

        $response->assertInertia(function (AssertableInertia $inertia) use (&$called) {
            $inertia->where('foo', 'bar');
            $inertia->missing('lazy1');
            $inertia->missing('lazy2');

            $inertia->reloadExcept('lazy1', function ($inertia) use (&$called) {
                $inertia->where('foo', 'bar');
                $inertia->missing('lazy1');
                $inertia->where('lazy2', 'qux');
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
                'lazy1' => Inertia::lazy(fn () => 'baz'),
                'lazy2' => Inertia::lazy(fn () => 'qux'),
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
}
