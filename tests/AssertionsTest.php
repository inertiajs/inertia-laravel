<?php

namespace Inertia\Tests;

use Inertia\Inertia;
use Illuminate\Foundation\Auth\User;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\AssertionFailedError;

class AssertionsTest extends TestCase
{
    public function test_the_view_is_served_by_inertia()
    {
        $response = $this->makeMockResponse(
            Inertia::render('test')
        );

        $response->assertInertia();
    }

    public function test_the_view_is_not_served_by_inertia()
    {
        $response = $this->makeMockResponse(view('welcome'));
        $response->assertOk(); // Make sure we can render the built-in Orchestra 'welcome' view..

        $this->expectException(AssertionFailedError::class);

        $response->assertInertia();
    }

    public function test_the_inertia_component_matches()
    {
        $response = $this->makeMockResponse(
            Inertia::render('test-component')
        );

        $response->assertInertiaComponent('test-component');
    }

    public function test_the_inertia_component_does_not_match()
    {
        $response = $this->makeMockResponse(
            Inertia::render('test-component')
        );

        $this->expectException(AssertionFailedError::class);

        $response->assertInertiaComponent('another-component');
    }

    public function test_the_inertia_page_has_a_prop()
    {
        $response = $this->makeMockResponse(
            Inertia::render('test-component', [
                'example-prop' => 'example-value',
            ])
        );

        $response->assertInertiaHas('example-prop');
    }

    public function test_the_inertia_page_does_not_have_a_prop()
    {
        $response = $this->makeMockResponse(
            Inertia::render('test-component', [
                'example-prop' => 'example-value',
            ])
        );

        $this->expectException(AssertionFailedError::class);

        $response->assertInertiaHas('another-prop');
    }

    public function test_the_inertia_page_has_a_nested_prop()
    {
        $response = $this->makeMockResponse(
            Inertia::render('test-component', [
                'example' => [
                    'nested' => 'nested-value',
                ],
            ])
        );

        $response->assertInertiaHas('example.nested');
    }

    public function test_the_inertia_page_does_not_have_a_nested_prop()
    {
        $response = $this->makeMockResponse(
            Inertia::render('test-component', [
                'example' => [
                    'nested' => 'nested-value',
                ],
            ])
        );

        $this->expectException(AssertionFailedError::class);

        $response->assertInertiaHas('example.another');
    }

    public function test_the_inertia_page_has_a_prop_that_matches_a_value()
    {
        $response = $this->makeMockResponse(
            Inertia::render('test-component', [
                'example-prop' => 'example-value',
            ])
        );

        $response->assertInertiaHas('example-prop', 'example-value');
    }

    public function test_the_inertia_page_has_a_prop_that_does_not_match_a_value()
    {
        $response = $this->makeMockResponse(
            Inertia::render('test-component', [
                'example-prop' => 'example-value',
            ])
        );

        $this->expectException(AssertionFailedError::class);

        $response->assertInertiaHas('example-prop', 'anohter-value');
    }

    public function test_the_inertia_page_has_a_nested_prop_that_matches_a_value()
    {
        $response = $this->makeMockResponse(
            Inertia::render('test-component', [
                'example' => [
                    'nested' => 'nested-value',
                ],
            ])
        );

        $response->assertInertiaHas('example.nested', 'nested-value');
    }

    public function test_the_inertia_page_has_a_nested_prop_that_does_not_match_a_value()
    {
        $response = $this->makeMockResponse(
            Inertia::render('test-component', [
                'example' => [
                    'nested' => 'nested-value',
                ],
            ])
        );

        $this->expectException(AssertionFailedError::class);

        $response->assertInertiaHas('example.nested', 'another-value');
    }

    public function test_the_inertia_page_has_a_prop_with_a_value_using_a_closure()
    {
        $response = $this->makeMockResponse(
            Inertia::render('test-component', [
                'example-prop' => 'example-value',
            ])
        );

        $response->assertInertiaHas('example-prop', function ($value) {
            return $value === 'example-value';
        });
    }

    public function test_the_inertia_page_does_not_have_a_prop_with_a_value_using_a_closure()
    {
        $response = $this->makeMockResponse(
            Inertia::render('test-component', [
                'example-prop' => 'example-value',
            ])
        );

        $this->expectException(AssertionFailedError::class);

        $response->assertInertiaHas('example-prop', function ($value) {
            return $value === 'another-value';
        });
    }

    public function test_the_inertia_page_has_a_nested_prop_with_a_value_using_a_closure()
    {
        $response = $this->makeMockResponse(
            Inertia::render('test-component', [
                'example' => [
                    'nested' => 'example-value',
                ],
            ])
        );

        $response->assertInertiaHas('example.nested', function ($value) {
            return $value === 'example-value';
        });
    }

    public function test_the_inertia_page_does_not_have_a_nested_prop_with_a_value_using_a_closure()
    {
        $response = $this->makeMockResponse(
            Inertia::render('test-component', [
                'example' => [
                    'nested' => 'example-value',
                ],
            ])
        );

        $this->expectException(AssertionFailedError::class);

        $response->assertInertiaHas('example.nested', function ($value) {
            return $value === 'another-value';
        });
    }

    public function test_the_inertia_page_has_a_prop_with_a_value_using_an_arrayable()
    {
        Model::unguard();
        $user = User::make(['name' => 'Example']);

        $response = $this->makeMockResponse(
            Inertia::render('test-component', [
                'example-prop' => $user,
            ])
        );

        $response->assertInertiaHas('example-prop', $user);
    }

    public function test_the_inertia_page_does_not_have_a_prop_with_a_value_using_an_arrayable()
    {
        Model::unguard();
        $userA = User::make(['name' => 'Example']);
        $userB = User::make(['name' => 'Another']);

        $response = $this->makeMockResponse(
            Inertia::render('test-component', [
                'example-prop' => $userA,
            ])
        );

        $this->expectException(AssertionFailedError::class);

        $response->assertInertiaHas('example-prop', $userB);
    }

    public function test_the_inertia_page_has_a_nested_prop_with_a_value_using_an_arrayable()
    {
        Model::unguard();
        $user = User::make(['name' => 'Example']);

        $response = $this->makeMockResponse(
            Inertia::render('test-component', [
                'example' => [
                    'nested' => $user,
                ],
            ])
        );

        $response->assertInertiaHas('example.nested', $user);
    }

    public function test_the_inertia_page_does_not_have_a_nested_prop_with_a_value_using_an_arrayable()
    {
        Model::unguard();
        $userA = User::make(['name' => 'Example']);
        $userB = User::make(['name' => 'Another']);

        $response = $this->makeMockResponse(
            Inertia::render('test-component', [
                'example' => [
                    'nested' => $userA,
                ],
            ])
        );

        $this->expectException(AssertionFailedError::class);

        $response->assertInertiaHas('example.nested', $userB);
    }

    public function test_the_inertia_page_has_all_the_given_props()
    {
        Model::unguard();
        $user = User::make(['name' => 'Example']);

        $response = $this->makeMockResponse(
            Inertia::render('test-component', [
                'foo' => 'bar',
                'baz' => [
                    'nested' => 'value',
                    'another' => $user,
                    'closure' => 'test',
                ],
            ])
        );

        $response->assertInertiaHasAll([
            'foo',
            'foo' => 'bar',
            'baz.nested' => 'value',
            'baz.another' => $user,
            'baz.closure' => function ($value) {
                return $value === 'test';
            },
        ]);
    }

    public function test_the_inertia_page_does_not_have_all_the_given_props()
    {
        $response = $this->makeMockResponse(
            Inertia::render('test-component', [
                'foo' => 'bar',
                'baz' => [
                    'nested' => 'value',
                ],
            ])
        );

        $this->expectException(AssertionFailedError::class);

        $response->assertInertiaHasAll([
            'foo' => 'bar',
            'baz.nested' => 'value',
            'missing-key',
        ]);
    }

    public function test_the_inertia_page_is_missing_a_prop()
    {
        $response = $this->makeMockResponse(
            Inertia::render('test-component', [
                'foo' => 'bar',
            ])
        );

        $response->assertInertiaMissing('baz');
    }

    public function test_the_inertia_page_is_not_missing_a_prop()
    {
        $response = $this->makeMockResponse(
            Inertia::render('test-component', [
                'foo' => 'bar',
            ])
        );

        $this->expectException(AssertionFailedError::class);

        $response->assertInertiaMissing('foo');
    }

    public function test_the_inertia_page_is_missing_a_nested_prop()
    {
        $response = $this->makeMockResponse(
            Inertia::render('test-component', [
                'foo' => 'bar',
                'baz' => [
                    'another' => 'value',
                ],
            ])
        );

        $response->assertInertiaMissing('baz.nested');
    }

    public function test_the_inertia_page_is_not_missing_a_nested_prop()
    {
        $response = $this->makeMockResponse(
            Inertia::render('test-component', [
                'foo' => 'bar',
                'baz' => [
                    'nested' => 'value',
                ],
            ])
        );

        $this->expectException(AssertionFailedError::class);

        $response->assertInertiaMissing('baz.nested');
    }

    private function makeMockResponse($view)
    {
        app('router')->get('/', function () use ($view) {
            return $view;
        });

        return $this->get('/');
    }
}
