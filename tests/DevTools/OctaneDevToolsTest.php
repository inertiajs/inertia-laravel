<?php

namespace Inertia\Tests\DevTools;

use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Facade;
use Inertia\DevTools\Data\IncomingEntry;
use Inertia\DevTools\EntryStore;
use Inertia\Tests\TestCase;

class OctaneDevToolsTest extends TestCase
{
    use InteractsWithDevToolsStorage;

    protected function defineEnvironment($app): void
    {
        $app['config']->set('inertia.devtools.enabled', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->bindEntriesRepository();

        EntryStore::resetCircuitBreaker();
    }

    protected function tearDown(): void
    {
        $this->clearDevToolsStorage();

        parent::tearDown();
    }

    public function test_entry_recorded_in_an_octane_sandbox_is_flushed_when_the_request_is_handled(): void
    {
        $entry = new IncomingEntry;
        $entry->component = 'Users/Index';

        $this->withOctaneSandbox(function (Application $sandbox) use ($entry) {
            $sandbox->make(EntryStore::class)->record($entry);

            // Octane hands the sandbox to the HTTP kernel, so the entry to persist lives on
            // the sandbox and not on the application the provider registered its listener on.
            $sandbox->make('events')->dispatch(new RequestHandled(Request::create('/'), new Response('ok')));
        });

        $saved = $this->latestRecordedEntry();

        $this->assertNotNull($saved);
        $this->assertSame('Users/Index', $saved['__meta']['component']);
    }

    /**
     * Run the callback against a clone of the application, the way Octane's Worker::handle()
     * serves every request from a sandbox container that it makes the current one.
     *
     * @see https://github.com/laravel/octane/blob/2.x/src/Worker.php
     */
    protected function withOctaneSandbox(callable $callback): void
    {
        $base = $this->app;

        $sandbox = clone $base;

        Container::setInstance($sandbox);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($sandbox);

        try {
            $callback($sandbox);
        } finally {
            Container::setInstance($base);
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($base);
        }
    }
}
