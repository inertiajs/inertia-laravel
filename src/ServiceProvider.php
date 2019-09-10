<?php

namespace Inertia;

use Illuminate\Routing\Router;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function boot()
    {
        $this->registerBladeDirective();
        $this->registerRouterMacro();
        $this->registerMiddleware();
        $this->registerConfig();
    }

    protected function registerBladeDirective()
    {
        Blade::directive('inertia', function () {
            return '<div id="app" data-page="{{ json_encode($page) }}"></div>';
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

    protected function registerConfig()
    {
        $source = __DIR__ . '/../config/inertia.php';

        $this->publishes([$source => config_path('inertia.php')]);
        $this->mergeConfigFrom($source, 'inertia');
    }
}
