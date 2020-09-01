<?php

namespace Inertia;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

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
        $this->shareValidationErrors();
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

    protected function shareValidationErrors()
    {
        if (Inertia::getShared('errors')) {
            return;
        }

        Inertia::share('errors', function () {
            return (object) array_map(function ($error) {
                return $error[0];
            }, Session::has('errors') ? Session::get('errors')->messages() : []);
        });
    }
}
