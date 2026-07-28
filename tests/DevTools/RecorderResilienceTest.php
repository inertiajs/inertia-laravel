<?php

namespace Inertia\Tests\DevTools;

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Middleware;
use Inertia\Tests\TestCase;

class RecorderResilienceTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        $this->clearDevToolsStorage();

        parent::tearDown();
    }

    public function test_a_misconfigured_except_list_does_not_break_the_response(): void
    {
        // Recording is a passive observer: a bad config value must drop the entry, not turn
        // every request in the app into a 500.
        config()->set('inertia.devtools.except', 'not-an-array');

        Route::middleware(Middleware::class)->get('/devtools-misconfigured', fn () => Inertia::render('Users/Index', ['name' => 'Alice']));

        $this->get('/devtools-misconfigured', ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonPath('props.name', 'Alice');
    }
}
