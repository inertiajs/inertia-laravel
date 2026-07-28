<?php

namespace Inertia\Tests\DevTools;

use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Gate;
use Inertia\Tests\TestCase;

class AuthorizeMiddlewareTest extends TestCase
{
    use InteractsWithDevToolsStorage;

    protected function defineEnvironment($app): void
    {
        $app['config']->set('inertia.devtools.enabled', true);
        $app['config']->set('inertia.devtools.gate', 'viewInertiaDevtools');
        $app['config']->set('inertia.devtools.middleware', [StartSession::class]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->bindEntriesRepository();
        $this->app['env'] = 'production';
    }

    protected function tearDown(): void
    {
        $this->clearDevToolsStorage();

        parent::tearDown();
    }

    public function test_the_configured_middleware_replaces_the_gate_default(): void
    {
        Gate::define('viewInertiaDevtools', fn ($user = null) => request()->hasSession());

        $this->getJson('/_inertia/devtools/entries')->assertOk();
    }

    public function test_authorization_still_runs_when_the_middleware_is_configured(): void
    {
        Gate::define('viewInertiaDevtools', fn ($user = null) => false);

        $this->getJson('/_inertia/devtools/entries')->assertForbidden();
    }
}
