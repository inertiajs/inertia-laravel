<?php

namespace Inertia;

use LogicException;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\App;
use Illuminate\Testing\TestResponse;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Illuminate\Foundation\Testing\TestResponse as LegacyTestResponse;

class ServiceProvider extends BaseServiceProvider
{
    public function register()
    {
        $this->app->singleton(ResponseFactory::class);
    }

    public function boot()
    {
        $this->registerBladeDirective();
        $this->registerRequestMacro();
        $this->registerRouterMacro();
        $this->registerMiddleware();

        if (App::runningUnitTests()) {
            $this->registerTestingMacros();
        }
    }

    protected function registerBladeDirective()
    {
        Blade::directive('inertia', function () {
            return '<div id="app" data-page="{{ json_encode($page) }}"></div>';
        });
    }

    protected function registerRequestMacro()
    {
        Request::macro('inertia', function () {
            return boolval($this->header('X-Inertia'));
        });
    }

    protected function registerRouterMacro()
    {
        Router::macro('inertia', function ($uri, $component, $props = []) {
            return $this->match(['GET', 'HEAD'], $uri, '\Inertia\Controller')
                ->defaults('component', $component)
                ->defaults('props', $props);
        });
    }

    protected function registerMiddleware()
    {
        $this->app[Kernel::class]->pushMiddleware(Middleware::class);
    }

    protected function registerTestingMacros()
    {
        // Laravel >= 7.0
        if (class_exists(TestResponse::class)) {
            TestResponse::mixin(new Assertions());

            return;
        }

        // Laravel <= 6.0
        if (class_exists(LegacyTestResponse::class)) {
            LegacyTestResponse::mixin(new Assertions());

            return;
        }

        throw new LogicException('Could not detect TestResponse class.');
    }
}
