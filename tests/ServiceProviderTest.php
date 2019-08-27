<?php

namespace Inertia\Tests;

use Illuminate\Routing\Router;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Inertia\ServiceProvider;
use Inertia\Middleware;

class ServiceProviderTest extends \Orchestra\Testbench\TestCase
{
    protected $serviceProvider;

    public function setUp(): void
    {
        parent::setUp();
        $this->serviceProvider = new ServiceProvider($this->app);
        $this->serviceProvider->register();
        // Used to reset a macro between tests due to their nature of being static
        Router::macro('unsetMacro', function ($key) {
            unset(self::$macros[$key]);
        });
    }

    public function test_publishing_config()
    {
        if (File::exists(config_path('inertia.php'))) {
            File::delete(config_path('inertia.php'));
        }

        $this->serviceProvider->boot();
        $this->artisan('vendor:publish', [
            '--provider' => ServiceProvider::class,
            '--tag' => 'config',
            '--force' => true,
        ])
            ->assertExitCode(0);

        $this->assertTrue(File::exists(config_path('inertia.php')));
    }

    public function test_router_macro_can_be_register()
    {
        Router::unsetMacro('inertia');
        $this->serviceProvider->boot();
        $this->assertTrue(Router::hasMacro('inertia'));

        $route = Route::inertia('/', 'User/Edit', ['user' => ['name' => 'Jonathan']]);
        $routes = Route::getRoutes();

        $this->assertNotEmpty($routes->getRoutes());
        $this->assertEquals($route, $routes->getRoutes()[0]);
        $this->assertEquals(['GET', 'HEAD'], $route->methods);
        $this->assertEquals('/', $route->uri);
        $this->assertEquals(
            ['uses' => '\Inertia\Controller@__invoke', 'controller' => '\Inertia\Controller'],
            $route->action
        );
        $this->assertEquals(
            ['component' => 'User/Edit', 'props' => ['user' => ['name' => 'Jonathan']]],
            $route->defaults
        );
    }

    public function test_middleware_can_be_registered()
    {
        $this->serviceProvider->boot();

        $kernel = App::make(Kernel::class);
        $this->assertTrue($kernel->hasMiddleware(Middleware::class));
    }

    public function test_blade_directive_can_be_registered()
    {
        $this->serviceProvider->boot();
        $directives = Blade::getCustomDirectives();
        $this->assertArrayHasKey('inertia', $directives);
        $this->assertEquals(
            '<div id="app" data-page="{{ json_encode($page) }}"></div>',
            $directives['inertia']()
        );
    }

    public function test_router_macro_can_be_disabled()
    {
        Router::unsetMacro('inertia');
        config()->set('inertia.use_router_macro', false);

        $this->serviceProvider->boot();
        $this->assertFalse(Router::hasMacro('inertia'));
    }

    public function test_blade_directive_can_be_disabled()
    {
        config()->set('inertia.use_blade_directive', false);

        $this->serviceProvider->boot();
        $this->assertArrayNotHasKey('inertia', Blade::getCustomDirectives());
    }

    public function test_middleware_can_be_disabled()
    {
        config()->set('inertia.use_middleware', false);

        $this->serviceProvider->boot();
        $kernel = App::make(Kernel::class);
        $this->assertFalse($kernel->hasMiddleware(Middleware::class));
    }
}