<?php

namespace Inertia\Tests\Testing;

use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;
use Inertia\Inertia;
use Inertia\Middleware;
use Inertia\Tests\TestCase;
use PHPUnit\Framework\AssertionFailedError;

class TestResponseMacrosTest extends TestCase
{
    public function test_it_can_make_inertia_assertions(): void
    {
        $response = $this->makeMockRequest(
            Inertia::render('foo')
        );

        $success = false;
        $response->assertInertia(function ($page) use (&$success) {
            $this->assertInstanceOf(AssertableJson::class, $page);
            $success = true;
        });

        $this->assertTrue($success);
    }

    public function test_it_preserves_the_ability_to_continue_chaining_laravel_test_response_calls(): void
    {
        $response = $this->makeMockRequest(
            Inertia::render('foo')
        );

        $this->assertInstanceOf(
            TestResponse::class,
            $response->assertInertia()
        );
    }

    public function test_it_can_retrieve_the_inertia_page(): void
    {
        $response = $this->makeMockRequest(
            Inertia::render('foo', ['bar' => 'baz'])
        );

        tap($response->inertiaPage(), function (array $page) {
            $this->assertSame('foo', $page['component']);
            $this->assertSame(['bar' => 'baz'], $page['props']);
            $this->assertSame('/example-url', $page['url']);
            $this->assertSame('', $page['version']);
            $this->assertFalse($page['encryptHistory']);
            $this->assertFalse($page['clearHistory']);
        });
    }

    public function test_it_can_retrieve_the_inertia_props(): void
    {
        $props = ['bar' => 'baz'];
        $response = $this->makeMockRequest(
            Inertia::render('foo', $props)
        );

        $this->assertSame($props, $response->inertiaProps());
    }

    public function test_it_can_retrieve_nested_inertia_prop_values_with_dot_notation(): void
    {
        $response = $this->makeMockRequest(
            Inertia::render('foo', [
                'bar' => ['baz' => 'qux'],
                'users' => [
                    ['name' => 'John'],
                    ['name' => 'Jane'],
                ],
            ])
        );

        $this->assertSame('qux', $response->inertiaProps('bar.baz'));
        $this->assertSame('John', $response->inertiaProps('users.0.name'));
    }

    public function test_it_can_assert_flash_data_on_redirect_responses(): void
    {
        $middleware = [StartSession::class, Middleware::class];

        Route::middleware($middleware)->post('/users', function () {
            return Inertia::flash([
                'message' => 'User created!',
                'notification' => ['type' => 'success'],
            ])->back();
        });

        $this->post('/users')
            ->assertRedirect()
            ->assertInertiaFlash('message')
            ->assertInertiaFlash('message', 'User created!')
            ->assertInertiaFlash('notification.type', 'success')
            ->assertInertiaFlashMissing('error')
            ->assertInertiaFlashMissing('notification.other');
    }

    public function test_assert_has_inertia_flash_fails_when_key_is_missing(): void
    {
        $middleware = [StartSession::class, Middleware::class];

        Route::middleware($middleware)->post('/users', function () {
            return Inertia::flash('message', 'Hello')->back();
        });

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Inertia Flash Data is missing key [other].');

        $this->post('/users')->assertInertiaFlash('other');
    }

    public function test_assert_has_inertia_flash_fails_when_value_does_not_match(): void
    {
        $middleware = [StartSession::class, Middleware::class];

        Route::middleware($middleware)->post('/users', function () {
            return Inertia::flash('message', 'Hello')->back();
        });

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Inertia Flash Data [message] does not match expected value.');

        $this->post('/users')->assertInertiaFlash('message', 'Different');
    }

    public function test_assert_missing_inertia_flash_fails_when_key_exists(): void
    {
        $middleware = [StartSession::class, Middleware::class];

        Route::middleware($middleware)->post('/users', function () {
            return Inertia::flash('message', 'Hello')->back();
        });

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Inertia Flash Data has unexpected key [message].');

        $this->post('/users')->assertInertiaFlashMissing('message');
    }
}
