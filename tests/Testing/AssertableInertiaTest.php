<?php

namespace Inertia\Tests\Testing;

use Inertia\Inertia;
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
}
