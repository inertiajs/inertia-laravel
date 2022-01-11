<?php

namespace Inertia\Tests;

use Illuminate\Foundation\Testing\TestResponse as LegacyTestResponse;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use Inertia\Inertia;
use Inertia\ServiceProvider;
use LogicException;
use Orchestra\Testbench\TestCase as Orchestra;

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
     * @return string
     *
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
