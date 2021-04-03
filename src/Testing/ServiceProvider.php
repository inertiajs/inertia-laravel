<?php

namespace Inertia\Testing;

use Illuminate\Foundation\Testing\TestResponse as LegacyTestResponse;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Illuminate\Testing\TestResponse;
use LogicException;

class ServiceProvider extends BaseServiceProvider
{
    public function register()
    {
        $this->app->singleton(Manager::class, function ($app) {
            return new Manager($app);
        });
    }

    public function boot()
    {
        $this->registerTestingMacros();
    }

    protected function registerTestingMacros()
    {
        if (class_exists(TestResponse::class)) {
            TestResponse::mixin(new TestResponseMacros());

            return;
        }

        // Laravel <= 6.0
        if (class_exists(LegacyTestResponse::class)) {
            LegacyTestResponse::mixin(new TestResponseMacros());

            return;
        }

        throw new LogicException('Could not detect TestResponse class.');
    }
}
