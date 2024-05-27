<?php

namespace Inertia\Tests\Testing;

use Illuminate\Testing\Fluent\AssertableJson;
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
            $this->getTestResponseClass(),
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
        });
    }
}
