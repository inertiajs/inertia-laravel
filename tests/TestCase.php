<?php

namespace Inertia\Tests;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use Inertia\Inertia;
use Inertia\ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * Example Page Objects.
     */
    protected const EXAMPLE_PAGE_OBJECT = ['component' => 'Foo/Bar', 'props' => ['foo' => 'bar'], 'url' => '/test', 'version' => ''];

    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/Stubs');

        Inertia::setRootView('welcome');
        Inertia::resolveComponentUsing();
        config()->set('inertia.testing.ensure_pages_exist', false);
        config()->set('inertia.pages.paths', [realpath(__DIR__)]);
    }

    /**
     * @param  class-string|array<int, class-string>  $middleware
     * @return TestResponse<Response>
     */
    protected function makeMockRequest(mixed $view, string|array $middleware = []): TestResponse
    {
        $middleware = is_array($middleware) ? $middleware : [$middleware];

        app('router')->middleware($middleware)->get('/example-url', function () use ($view) {
            return is_callable($view) ? $view() : $view;
        });

        return $this->get('/example-url');
    }
}
