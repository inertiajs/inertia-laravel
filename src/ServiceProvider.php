<?php

namespace Inertia;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Perform post-registration booting of services.
     */
    public function boot()
    {
        Blade::directive('inertia', function () {
            return '<div id="app" data-page="{{ json_encode($page) }}"></div>';
        });

        Router::macro('inertia', function ($uri, $component, $props = []) {
            return $this->match(['GET', 'HEAD'], $uri, '\Inertia\Controller')
                ->defaults('component', $component)
                ->defaults('props', $props);
        });
    }
}
