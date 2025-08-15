<?php

namespace Inertia;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Illuminate\Testing\TestResponse;
use Illuminate\View\FileViewFinder;
use Inertia\Ssr\Gateway;
use Inertia\Ssr\HttpGateway;
use Inertia\Support\Header;
use Inertia\Testing\TestResponseMacros;
use LogicException;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton(ResponseFactory::class);
        $this->app->bind(Gateway::class, HttpGateway::class);

        $this->mergeConfigFrom(
            __DIR__.'/../config/inertia.php',
            'inertia'
        );

        $this->registerBladeDirectives();
        $this->registerRequestMacro();
        $this->registerRouterMacro();
        $this->registerTestingMacros();
        $this->registerMiddleware();

        $this->app->bind('inertia.view-finder', function ($app) {
            return new FileViewFinder(
                $app['files'],
                $app['config']->get('inertia.page_paths'),
                $app['config']->get('inertia.page_extensions')
            );
        });

        $this->app->bind('inertia.testing.view-finder', function ($app) {
            return new FileViewFinder(
                $app['files'],
                $app['config']->get('inertia.testing.page_paths'),
                $app['config']->get('inertia.testing.page_extensions')
            );
        });
    }

    /**
     * Boot the service provider.
     */
    public function boot(): void
    {
        $this->registerConsoleCommands();

        $this->publishes([
            __DIR__.'/../config/inertia.php' => config_path('inertia.php'),
        ]);
    }

    /**
     * Register @inertia and @inertiaHead directives for rendering the Inertia
     * root element and SSR head content in Blade templates.
     */
    protected function registerBladeDirectives(): void
    {
        $this->callAfterResolving('blade.compiler', function ($blade) {
            $blade->directive('inertia', [Directive::class, 'compile']);
            $blade->directive('inertiaHead', [Directive::class, 'compileHead']);
        });
    }

    /**
     * Register Artisan commands for managing Inertia middleware creation
     * and server-side rendering operations when running in console mode.
     */
    protected function registerConsoleCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            Commands\CreateMiddleware::class,
            Commands\StartSsr::class,
            Commands\StopSsr::class,
            Commands\CheckSsr::class,
        ]);
    }

    /**
     * Add an 'inertia' method to the Request class that returns true
     * if the current request is an Inertia request.
     */
    protected function registerRequestMacro(): void
    {
        Request::macro('inertia', function () {
            return (bool) $this->header(Header::INERTIA);
        });
    }

    /**
     * Register the router macro.
     */
    protected function registerRouterMacro(): void
    {
        /**
         * @param  array<array-key, mixed>  $props
         */
        Router::macro('inertia', function ($uri, $component, $props = []) {
            return $this->match(['GET', 'HEAD'], $uri, '\\'.Controller::class)
                ->defaults('component', $component)
                ->defaults('props', $props);
        });
    }

    /**
     * Register the testing macros.
     *
     * @throws \LogicException
     */
    protected function registerTestingMacros(): void
    {
        if (class_exists(TestResponse::class)) {
            TestResponse::mixin(new TestResponseMacros);

            return;
        }

        throw new LogicException('Could not detect TestResponse class.');
    }

    /**
     * Register the middleware aliases.
     */
    protected function registerMiddleware(): void
    {
        $this->app['router']->aliasMiddleware(
            'inertia.encrypt',
            EncryptHistoryMiddleware::class
        );
    }
}
