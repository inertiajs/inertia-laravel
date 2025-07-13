<?php

namespace Inertia\Tests;

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
    protected const EXAMPLE_PAGE_OBJECT = ['component' => 'Foo/Bar', 'props' => ['foo' => 'bar'], 'url' => '/test', 'version' => '', 'encryptHistory' => false, 'clearHistory' => false];

    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/Stubs');

        Inertia::setRootView('welcome');
        config()->set('inertia.testing.ensure_pages_exist', false);
        config()->set('inertia.testing.page_paths', [realpath(__DIR__)]);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
        ];
    }

    protected function makeMockRequest($view): TestResponse
    {
        app('router')->get('/example-url', function () use ($view) {
            return is_callable($view) ? $view() : $view;
        });

        return $this->get('/example-url');
    }
}
