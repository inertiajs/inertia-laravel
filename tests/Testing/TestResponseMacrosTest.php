<?php

namespace Inertia\Tests\Testing;

use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;
use Inertia\Inertia;
use Inertia\Tests\TestCase;

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
}
