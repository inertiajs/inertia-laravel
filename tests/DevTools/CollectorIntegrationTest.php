<?php

namespace Inertia\Tests\DevTools;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\DevTools\EntryStore;
use Inertia\Inertia;
use Inertia\Middleware;
use Inertia\Response;
use Inertia\Tests\TestCase;

class CollectorIntegrationTest extends TestCase
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

    /**
     * Assert a resolved source location points at a line containing the given text.
     *
     * This avoids hardcoding line numbers: reformatting the file moves both the code and
     * the resolved line together, so the assertion stays valid.
     *
     * @param  array{file: string, line: int}  $source
     */
    private function assertSourceLineContains(array $source, string $needle): void
    {
        $lines = file($source['file']);

        $this->assertArrayHasKey($source['line'] - 1, $lines, "No line {$source['line']} in {$source['file']}");
        $this->assertStringContainsString($needle, $lines[$source['line'] - 1]);
    }

    public function test_collector_payload_reaches_recorder_and_does_not_leak_into_page_json(): void
    {
        Route::middleware(Middleware::class)
            ->get('/collector-route', fn () => Inertia::render('Users/Index', ['name' => 'Alice']))
            ->name('users.index');

        $response = $this->get('/collector-route', ['X-Inertia' => 'true', 'X-Inertia-Version' => '']);

        $response->assertOk();

        $json = $response->json();
        $this->assertArrayNotHasKey('devtools', $json);

        $this->app->make(EntryStore::class)->flush($this->repo);
        $this->assertCount(1, $this->recordedEntries());

        $entry = $this->latestRecordedEntry();

        $this->assertSame('Users/Index', $entry['__meta']['component']);
        $this->assertSame('users.index', $entry['route']['name']);
        $this->assertSame('/collector-route', $entry['route']['uri']);
        $this->assertSame('present', $entry['http']['responseBody']['status']);
        $this->assertSame('Users/Index', $entry['http']['responseBody']['value']['component']);
        $this->assertSame('Alice', $entry['http']['responseBody']['value']['props']['name']);
    }

    public function test_props_are_populated_with_inertia_metadata(): void
    {
        Route::middleware(Middleware::class)->get('/props-route', fn () => Inertia::render('Users/Index', [
            'name' => 'Alice',
            'tags' => ['a', 'b'],
            'auth' => ['user' => ['id' => 1, 'name' => 'John']],
            'lazy' => Inertia::optional(fn () => 'never resolved'),
            'eager' => Inertia::always(fn () => 'always there'),
        ]));

        $this->get('/props-route', ['X-Inertia' => 'true', 'X-Inertia-Version' => '']);

        $this->app->make(EntryStore::class)->flush($this->repo);

        $entry = $this->latestRecordedEntry();
        $props = $entry['props'];

        $this->assertArrayHasKey('name', $props);
        $this->assertArrayNotHasKey('phpType', $props['name']);
        $this->assertFalse($props['name']['shared']);
        $this->assertSame('Alice', $entry['propValues']['name']);

        $this->assertArrayHasKey('tags', $props);
        $this->assertArrayNotHasKey('phpType', $props['tags']);
        $this->assertArrayNotHasKey('count', $props['tags']);
        $this->assertArrayNotHasKey('model', $props['tags']);
        $this->assertSame(['a', 'b'], $entry['propValues']['tags']);
        $this->assertArrayNotHasKey('tags.0', $props);
        $this->assertArrayNotHasKey('tags.1', $props);
        $this->assertArrayNotHasKey('tags.0', $entry['propValues']);
        $this->assertArrayNotHasKey('tags.1', $entry['propValues']);

        $this->assertSame(['user' => ['id' => 1, 'name' => 'John']], $entry['propValues']['auth']);
        $this->assertArrayNotHasKey('auth.user', $entry['propValues']);
        $this->assertArrayNotHasKey('auth.user.id', $entry['propValues']);
        $this->assertArrayNotHasKey('auth.user.name', $entry['propValues']);

        $this->assertArrayHasKey('eager', $props);
        $this->assertSame('always', $props['eager']['inertiaType']);
        $this->assertSame('always there', $entry['propValues']['eager']);

        $this->assertArrayNotHasKey('lazy', $props);
    }

    public function test_prop_values_are_redacted(): void
    {
        Route::middleware(Middleware::class)->get('/redact-route', fn () => Inertia::render('Users/Index', [
            'token' => 'super-secret',
            'auth' => ['user' => ['name' => 'John', 'api_key' => 'xyz']],
        ]));

        $this->get('/redact-route', ['X-Inertia' => 'true', 'X-Inertia-Version' => '']);

        $this->app->make(EntryStore::class)->flush($this->repo);

        $propValues = $this->latestRecordedEntry()['propValues'];

        $this->assertSame('[REDACTED]', $propValues['token']);
        $this->assertSame('John', $propValues['auth']['user']['name']);
        $this->assertSame('[REDACTED]', $propValues['auth']['user']['api_key']);
    }

    public function test_merge_direction_and_deep_merge_are_recorded(): void
    {
        Route::middleware(Middleware::class)->get('/merge-route', fn () => Inertia::render('Users/Index', [
            'appended' => Inertia::merge(['a']),
            'prepended' => Inertia::merge(['b'])->prepend(),
            'deepAppended' => Inertia::merge(['c' => 1])->deepMerge(),
            'deepPrepended' => Inertia::merge(['d' => 1])->deepMerge()->prepend(),
            'matched' => Inertia::merge([['id' => 1]])->matchOn('id'),
        ]));

        $this->get('/merge-route', ['X-Inertia' => 'true', 'X-Inertia-Version' => '']);

        $this->app->make(EntryStore::class)->flush($this->repo);

        $props = $this->latestRecordedEntry()['props'];

        $this->assertSame('merge', $props['appended']['inertiaType']);
        $this->assertSame('append', $props['appended']['mergeDirection']);
        $this->assertArrayNotHasKey('deepMerge', $props['appended']);

        $this->assertSame('prepend', $props['prepended']['mergeDirection']);
        $this->assertArrayNotHasKey('deepMerge', $props['prepended']);

        $this->assertSame('append', $props['deepAppended']['mergeDirection']);
        $this->assertTrue($props['deepAppended']['deepMerge']);

        $this->assertSame('prepend', $props['deepPrepended']['mergeDirection']);
        $this->assertTrue($props['deepPrepended']['deepMerge']);

        // matchOn() upserts by key, so it reads as a deep merge in the panel.
        $this->assertSame('append', $props['matched']['mergeDirection']);
        $this->assertTrue($props['matched']['deepMerge']);
    }

    public function test_deferred_prop_reloaded_outside_a_deferred_request_reads_as_regular(): void
    {
        Route::middleware(Middleware::class)->get('/defer-reload-route', fn () => Inertia::render('Users/Index', [
            'lazy' => Inertia::defer(fn () => 'loaded', 'groupA'),
        ]));

        // Manual partial reload (no devtools-deferred header): the DeferProp is delivered like a
        // regular partial prop, so it carries no defer type or group.
        $this->get('/defer-reload-route', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => '',
            'X-Inertia-Partial-Component' => 'Users/Index',
            'X-Inertia-Partial-Data' => 'lazy',
        ]);
        $this->app->make(EntryStore::class)->flush($this->repo);

        $lazy = $this->latestRecordedEntry()['props']['lazy'];
        $this->assertNull($lazy['inertiaType']);
        $this->assertArrayNotHasKey('deferGroup', $lazy);

        // Deferred auto-load (devtools-deferred header): the prop reads as deferred with its group.
        $this->get('/defer-reload-route', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => '',
            'X-Inertia-Partial-Component' => 'Users/Index',
            'X-Inertia-Partial-Data' => 'lazy',
            'X-Inertia-Devtools-Deferred' => '1',
        ]);
        $this->app->make(EntryStore::class)->flush($this->repo);

        $lazy = $this->latestRecordedEntry()['props']['lazy'];
        $this->assertSame('defer', $lazy['inertiaType']);
        $this->assertSame('groupA', $lazy['deferGroup']);
    }

    public function test_rescued_deferred_prop_is_flagged(): void
    {
        Route::middleware(Middleware::class)->get('/rescue-route', fn () => Inertia::render('Users/Index', [
            'flaky' => Inertia::defer(fn () => throw new \RuntimeException('boom'), rescue: true)->live(on: 'order.updated', channel: 'orders.1'),
        ]));

        $this->get('/rescue-route', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => '',
            'X-Inertia-Partial-Component' => 'Users/Index',
            'X-Inertia-Partial-Data' => 'flaky',
            'X-Inertia-Devtools-Deferred' => '1',
        ]);

        $this->app->make(EntryStore::class)->flush($this->repo);

        $entry = $this->latestRecordedEntry();

        $this->assertSame('defer', $entry['props']['flaky']['inertiaType']);
        $this->assertTrue($entry['props']['flaky']['rescued']);
        // The prop is still live, whatever its resolver did
        $this->assertTrue($entry['props']['flaky']['live']);
        $this->assertArrayNotHasKey('flaky', $entry['propValues'] ?? []);
    }

    public function test_once_props_are_flagged(): void
    {
        Route::middleware(Middleware::class)->get('/once-route', fn () => Inertia::render('Users/Index', [
            'config' => Inertia::once(fn () => 'cached'),
            'plain' => 'nothing',
        ]));

        $this->get('/once-route', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => '',
        ]);

        $this->app->make(EntryStore::class)->flush($this->repo);

        $props = $this->latestRecordedEntry()['props'];

        $this->assertTrue($props['config']['once']);
        $this->assertArrayNotHasKey('once', $props['plain']);
    }

    public function test_live_props_are_flagged_without_losing_their_wrapper_type(): void
    {
        Route::middleware(Middleware::class)->get('/live-route', fn () => Inertia::render('Users/Index', [
            'order' => Inertia::live('shipped', 'order.updated', 'orders.1'),
            'stats' => Inertia::always(fn () => 'up')->live('order.updated', 'orders.1'),
            'plain' => 'nothing',
        ]));

        $this->get('/live-route', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => '',
        ]);

        $this->app->make(EntryStore::class)->flush($this->repo);

        $props = $this->latestRecordedEntry()['props'];

        $this->assertNull($props['order']['inertiaType']);
        $this->assertTrue($props['order']['live']);

        $this->assertSame('always', $props['stats']['inertiaType']);
        $this->assertTrue($props['stats']['live']);

        $this->assertArrayNotHasKey('live', $props['plain']);
    }

    public function test_share_sources_populate_when_share_is_called(): void
    {
        Inertia::share('flash', 'hello');

        Route::middleware(Middleware::class)->get('/share-route', fn () => Inertia::render('Users/Index', ['name' => 'Alice']));

        $this->get('/share-route', ['X-Inertia' => 'true', 'X-Inertia-Version' => '']);

        $this->app->make(EntryStore::class)->flush($this->repo);

        $entry = $this->latestRecordedEntry();

        $this->assertTrue($entry['props']['flash']['shared']);
        $this->assertArrayHasKey('file', $entry['props']['flash']['shareSource']);
        $this->assertArrayHasKey('line', $entry['props']['flash']['shareSource']);
    }

    public function test_share_sources_resolve_each_array_key_line(): void
    {
        Inertia::share([
            'first_shared' => 'one',
            'second_shared' => 'two',
        ]);

        Route::middleware(Middleware::class)->get('/share-lines-route', fn () => Inertia::render('Users/Index', ['name' => 'Jane']));

        $this->get('/share-lines-route', ['X-Inertia' => 'true', 'X-Inertia-Version' => ''])->assertOk();

        $this->app->make(EntryStore::class)->flush($this->repo);

        $entry = $this->latestRecordedEntry();
        $first = $entry['props']['first_shared']['shareSource'];
        $second = $entry['props']['second_shared']['shareSource'];

        $this->assertSame(__FILE__, $first['file']);
        $this->assertSourceLineContains($first, "'first_shared' => 'one'");
        $this->assertSame(__FILE__, $second['file']);
        $this->assertSourceLineContains($second, "'second_shared' => 'two'");
        $this->assertNotSame($first['line'], $second['line']);
    }

    public function test_middleware_share_sources_resolve_each_share_method_key_line(): void
    {
        Route::middleware(DevToolsSharedSourceMiddleware::class)->get('/middleware-share-lines-route', fn () => Inertia::render('Users/Index', ['name' => 'Jane']));

        $this->get('/middleware-share-lines-route', ['X-Inertia' => 'true', 'X-Inertia-Version' => ''])->assertOk();

        $this->app->make(EntryStore::class)->flush($this->repo);

        $entry = $this->latestRecordedEntry();
        $first = $entry['props']['middleware_first_shared']['shareSource'];
        $second = $entry['props']['middleware_second_shared']['shareSource'];

        $this->assertSame(__FILE__, $first['file']);
        $this->assertSourceLineContains($first, "'middleware_first_shared' => 'one'");
        $this->assertSame(__FILE__, $second['file']);
        $this->assertSourceLineContains($second, "'middleware_second_shared' => 'two'");
        $this->assertNotSame($first['line'], $second['line']);
    }

    public function test_middleware_share_sources_resolve_parent_share_method_keys(): void
    {
        Route::middleware(DevToolsChildSharedSourceMiddleware::class)->get('/middleware-parent-share-lines-route', fn () => Inertia::render('Users/Index', ['name' => 'Jane']));

        $this->get('/middleware-parent-share-lines-route', ['X-Inertia' => 'true', 'X-Inertia-Version' => ''])->assertOk();

        $this->app->make(EntryStore::class)->flush($this->repo);

        $entry = $this->latestRecordedEntry();
        $parent = $entry['props']['parent_shared']['shareSource'];
        $child = $entry['props']['child_shared']['shareSource'];

        $this->assertSame(__FILE__, $parent['file']);
        $this->assertSourceLineContains($parent, "'parent_shared' => 'parent'");
        $this->assertSame(__FILE__, $child['file']);
        $this->assertSourceLineContains($child, "'child_shared' => 'child'");
        $this->assertNotSame($parent['line'], $child['line']);
    }

    public function test_render_source_resolves_to_the_render_call_site(): void
    {
        Route::middleware(Middleware::class)
            ->get('/render-source-route', fn () => Inertia::render('Users/Index', ['name' => 'Alice']));

        $this->get('/render-source-route', ['X-Inertia' => 'true', 'X-Inertia-Version' => ''])->assertOk();

        $this->app->make(EntryStore::class)->flush($this->repo);

        $entry = $this->latestRecordedEntry();

        $this->assertSame(__FILE__, $entry['renderSource']['file']);
        $this->assertSourceLineContains($entry['renderSource'], "Inertia::render('Users/Index'");
    }

    public function test_render_source_resolves_to_the_route_definition_for_route_defined_inertia_renders(): void
    {
        Route::inertia('/route-inertia', 'Users/Index', ['name' => 'Alice'])->middleware(Middleware::class);

        $this->get('/route-inertia', ['X-Inertia' => 'true', 'X-Inertia-Version' => ''])->assertOk();

        $this->app->make(EntryStore::class)->flush($this->repo);

        $entry = $this->latestRecordedEntry();

        $this->assertSame(__FILE__, $entry['renderSource']['file']);
        $this->assertSourceLineContains($entry['renderSource'], "Route::inertia('/route-inertia'");
    }

    public function test_action_source_resolves_for_invokable_controllers(): void
    {
        Route::middleware(Middleware::class)->get('/invokable-route', DevToolsInvokableController::class);

        $this->get('/invokable-route', ['X-Inertia' => 'true', 'X-Inertia-Version' => ''])->assertOk();

        $this->app->make(EntryStore::class)->flush($this->repo);

        $entry = $this->latestRecordedEntry();

        $this->assertSame(__FILE__, $entry['route']['actionSource']['file']);
        $this->assertSourceLineContains($entry['route']['actionSource'], 'function __invoke');
    }
}

class DevToolsInvokableController
{
    public function __invoke(): Response
    {
        return Inertia::render('Users/Index', ['name' => 'Alice']);
    }
}

class DevToolsSharedSourceMiddleware extends Middleware
{
    public function share(Request $request)
    {
        return array_merge(parent::share($request), [
            'middleware_first_shared' => 'one',
            'middleware_second_shared' => 'two',
        ]);
    }
}

class DevToolsParentSharedSourceMiddleware extends Middleware
{
    public function share(Request $request)
    {
        return [
            'parent_shared' => 'parent',
        ];
    }
}

class DevToolsChildSharedSourceMiddleware extends DevToolsParentSharedSourceMiddleware
{
    public function share(Request $request)
    {
        return [
            ...parent::share($request),
            'child_shared' => 'child',
        ];
    }
}
