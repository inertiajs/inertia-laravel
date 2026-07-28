<?php

namespace Inertia\Tests\DevTools;

use Illuminate\Support\Facades\Route;
use Inertia\DevTools\DevToolsHeader;
use Inertia\DevTools\EntryStore;
use Inertia\Inertia;
use Inertia\Middleware;
use Inertia\Tests\TestCase;

class MiddlewareDevToolsDisabledTest extends TestCase
{
    use InteractsWithDevToolsStorage;

    protected function defineEnvironment($app): void
    {
        $app['config']->set('inertia.devtools.enabled', false);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->bindEntriesRepository();
    }

    protected function tearDown(): void
    {
        $this->clearDevToolsStorage();

        parent::tearDown();
    }

    public function test_nothing_is_recorded_when_devtools_is_disabled(): void
    {
        Route::middleware(Middleware::class)->get('/devtools-off', fn () => Inertia::render('Users/Index', ['name' => 'Alice']));

        $response = $this->get('/devtools-off');

        $response->assertOk();
        $this->assertNull($response->headers->get(DevToolsHeader::DEVTOOLS_ID));
        $this->assertStringNotContainsString('data-inertia-devtools-id', (string) $response->getContent());

        $this->app->make(EntryStore::class)->flush($this->repo);

        $this->assertSame([], $this->repo->all());
    }

    public function test_the_entry_endpoints_are_not_registered_when_devtools_is_disabled(): void
    {
        $this->getJson('/_inertia/devtools/entries')->assertNotFound();
    }
}
