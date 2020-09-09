<?php

namespace Inertia;

use LogicException;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Testing\TestResponse;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Session;
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
        $this->shareValidationErrors();

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
    
    protected function shareValidationErrors()
    {
        if (Inertia::getShared('errors')) {
            return;
        }

        Inertia::share('errors', function () {
            if (! Session::has('errors')) {
                return (object) [];
            }

            return (object) Collection::make(Session::get('errors')->getBags())->map(function ($bag) {
                return (object) Collection::make($bag->messages())->map(function ($errors) {
                    return $errors[0];
                })->toArray();
            })->pipe(function ($bags) {
                return $bags->has('default') ? $bags->get('default') : $bags->toArray();
            });
        });
    }
}
