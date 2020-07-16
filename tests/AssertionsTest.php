<?php

namespace Inertia\Tests;

use Inertia\Inertia;
use Illuminate\Foundation\Auth\User;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\AssertionFailedError;

class AssertionsTest extends TestCase
{
    private function makeMockResponse($view)
    {
        app('router')->get('/', function () use ($view) {
            return $view;
        });

        return $this->get('/');
    }

    public function test_the_view_is_inertia()
    {
        $response = $this->makeMockResponse(
            Inertia::render('test')
        );

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
        $this->expectException(AssertionFailedError::class);
        $response = $this->makeMockResponse(
            Inertia::render('test-component')
        );

        $response->assertInertiaComponent('wrong-component');
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

    public function test_the_inertia_page_has_a_prop_that_matches_a_value()
    {
        $response = $this->makeMockResponse(
            Inertia::render('test-component', [
                'example-prop' => 'example-value',
            ])
        );

        $response->assertInertiaHas('example-prop', 'example-value');
    }

    public function test_the_inertia_page_has_a_nested_prop_that_matches_a_value()
    {
        $response = $this->makeMockResponse(
            Inertia::render('test-component', [
                'example' => [
                    'nested' => 'nested-value'
                ],
            ])
        );

        $response->assertInertiaHas('example.nested', 'nested-value');
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

    public function test_the_inertia_page_has_a_prop_with_a_value_using_a_model()
    {
        Model::unguard();
        $user = User::make(['name' => 'Example']);

        $response = $this->makeMockResponse(
            Inertia::render('test-component', [
                'example-prop' => $user,
            ])
        );

        $response->assertInertiaHas('example-prop', $user);
        $response->assertInertiaHas('example-prop', ['name' => 'Example']);
    }
}
