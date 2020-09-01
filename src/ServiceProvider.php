<?php

namespace Inertia;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
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
            $errors = Session::get('errors', new ViewErrorBag());

            if (is_array($errors) && count($errors) > 0) {
                $errors = (new ViewErrorBag())->put('default', new MessageBag($errors));
            } elseif ($errors instanceof MessageBag && $errors->any()) {
                $errors = (new ViewErrorBag())->put('default', $errors);
            } elseif (! $errors instanceof ViewErrorBag) {
                return (object) [];
            }

            $formatted = collect($errors->getBags())->map(function (MessageBag $bag) {
                return collect($bag->messages())->map(function ($errors) {
                    return $errors[0];
                });
            });

            if ($formatted->count() === 1 && $formatted->has('default')) {
                return (object) $formatted->toArray()['default'];
            }

            return (object) $formatted->toArray();
        });
    }
}
