<?php

namespace Inertia;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ServiceProvider extends BaseServiceProvider
{
    public function register()
    {
        $this->app->singleton(ResponseFactory::class);

        $this->registerExceptionHandlers();
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
        $this->app[Kernel::class]->appendMiddlewareToGroup(
            Config::get('inertia.middleware_group', 'web'),
            Middleware::class
        );
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

    protected function registerExceptionHandlers()
    {
        $handler = app(ExceptionHandler::class);

        // The 'renderable' method is only available as of Laravel 8+
        // In earlier version of Laravel, you'll have to register
        // these yourself in your app's exception handler.
        if (! method_exists($handler, 'renderable')) {
            return;
        }

        $handler->renderable(function (HttpExceptionInterface $exception) {
            if ($exception->getStatusCode() === 419 && $exception->getPrevious() instanceof TokenMismatchException) {
                return Redirect::back()->with([
                    'error' => Config::get('inertia.csrf_error', 'The page expired, please try again.'),
                ]);
            }
        });
    }
}
