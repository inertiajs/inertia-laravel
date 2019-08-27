<?php

namespace Inertia;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    private static $configPath = __DIR__ . '/../config/config.php';

    public function boot()
    {
        $this->publishes([self::$configPath => config_path('inertia.php')], 'config');

        if (config('inertia.use_blade_directive', true)) {
            $this->registerBladeDirective();
        }
        if (config('inertia.use_router_macro', true)) {
            $this->registerRouterMacro();
        }
        if (config('inertia.use_middleware', true)) {
            $this->registerMiddleware();
        }
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

    public function register()
    {
        parent::register();
        $this->mergeConfigFrom(self::$configPath, 'inertia');
    }
}
