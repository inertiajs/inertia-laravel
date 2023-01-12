<?php

namespace Inertia\Tests;

use LogicException;
use Inertia\Inertia;
use Inertia\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use Orchestra\Testbench\TestCase as Orchestra;
use Illuminate\Foundation\Testing\TestResponse as LegacyTestResponse;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
        ];
    }

    public function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/Stubs');

        Inertia::setRootView('welcome');
        config()->set('inertia.testing.ensure_pages_exist', false);
        config()->set('inertia.testing.page_paths', [realpath(__DIR__)]);
    }

    /**
     * @throws LogicException
     */
    protected function getTestResponseClass(): string
    {
        // Laravel >= 7.0
        if (class_exists(TestResponse::class)) {
            return TestResponse::class;
        }

        // Laravel <= 6.0
        if (class_exists(LegacyTestResponse::class)) {
            return LegacyTestResponse::class;
        }

        throw new LogicException('Could not detect TestResponse class.');
    }

    /** @returns TestResponse|LegacyTestResponse */
    protected function makeMockRequest($view)
    {
        app('router')->get('/example-url', function () use ($view) {
            return $view;
        });

        return $this->get('/example-url');
    }
}
