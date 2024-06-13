<?php

namespace Inertia;

use LogicException;
use Inertia\Ssr\Gateway;
use ReflectionException;
use Inertia\Support\Header;
use Illuminate\Http\Request;
use Inertia\Ssr\HttpGateway;
use Illuminate\Routing\Router;
use Illuminate\View\FileViewFinder;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\TestResponseMacros;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Illuminate\Foundation\Testing\TestResponse as LegacyTestResponse;

class ServiceProvider extends BaseServiceProvider
{
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

        $this->app->bind('inertia.testing.view-finder', function ($app) {
            return new FileViewFinder(
                $app['files'],
                $app['config']->get('inertia.testing.page_paths'),
                $app['config']->get('inertia.testing.page_extensions')
            );
        });
    }

    public function boot(): void
    {
        $this->registerConsoleCommands();

        $this->publishes([
            __DIR__.'/../config/inertia.php' => config_path('inertia.php'),
        ]);
    }

    protected function registerBladeDirectives(): void
    {
        $this->callAfterResolving('blade.compiler', function ($blade) {
            $blade->directive('inertia', [Directive::class, 'compile']);
            $blade->directive('inertiaHead', [Directive::class, 'compileHead']);
        });
    }

    protected function registerConsoleCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            Commands\CreateMiddleware::class,
            Commands\StartSsr::class,
            Commands\StopSsr::class,
        ]);
    }

    protected function registerRequestMacro(): void
    {
        Request::macro('inertia', function () {
            return (bool) $this->header(Header::INERTIA);
        });
    }

    protected function registerRouterMacro(): void
    {
        Router::macro('inertia', function ($uri, $component, $props = []) {
            return $this->match(['GET', 'HEAD'], $uri, '\\'.Controller::class)
                ->defaults('component', $component)
                ->defaults('props', $props);
        });
    }

    /**
     * @throws ReflectionException|LogicException
     */
    protected function registerTestingMacros(): void
    {
        if (class_exists(TestResponse::class)) {
            TestResponse::mixin(new TestResponseMacros());

            return;
        }

        // Laravel <= 6.0
        if (class_exists(LegacyTestResponse::class)) {
            LegacyTestResponse::mixin(new TestResponseMacros());

            return;
        }

        throw new LogicException('Could not detect TestResponse class.');
    }
}
