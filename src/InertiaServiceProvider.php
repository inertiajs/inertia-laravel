<?php

namespace Inertia;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class InertiaServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        Blade::directive('inertia', function () {
            return "<div id=\"app\" data-component=\"{{ \$component }}\" data-props=\"{{ json_encode((object) \$props) }}\"></div>";
        });
    }

    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
