<?php

namespace Inertia\Tests\DevTools;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Tests\TestCase;

class FlashDataTest extends TestCase
{
    use InteractsWithDevToolsStorage;

    protected function defineEnvironment($app): void
    {
        // The entry endpoints run the `web` middleware group, which encrypts cookies.
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('inertia.devtools.enabled', true);
        $app['config']->set('inertia.devtools.gate', 'viewInertiaDevtools');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->bindEntriesRepository();
        Gate::define('viewInertiaDevtools', fn ($user = null) => true);
    }

    protected function tearDown(): void
    {
        $this->clearDevToolsStorage();

        parent::tearDown();
    }

    public function test_validation_errors_survive_the_entry_request_racing_the_redirect(): void
    {
        Route::middleware('web')->post('/users', fn (Request $request) => $request->validate(['name' => 'required']));
        Route::middleware('web')->get('/users/create', fn () => session('errors')?->get('name') ?? []);

        $this->post('/users')->assertStatus(302);

        // The extension fetches the entry the moment the failed POST responds, so this lands
        // between the redirect and the request the browser makes to follow it.
        $this->getJson('/_inertia/devtools/entries')->assertOk();
        $this->getJson('/_inertia/devtools/entries/'.$this->savedEntryId())->assertOk();

        $this->get('/users/create')->assertSee('The name field is required.');
    }

    public function test_flashed_data_is_still_read_once_by_the_app(): void
    {
        Route::middleware('web')->get('/app-page', fn () => (string) session('status'));

        $this->session(['status' => 'saved', '_flash' => ['old' => ['status'], 'new' => []]]);

        $this->getJson('/_inertia/devtools/entries')->assertOk();

        $this->get('/app-page')->assertSee('saved');
        $this->get('/app-page')->assertDontSee('saved');
    }

    protected function savedEntryId(): string
    {
        $id = (string) Str::ulid();

        $this->repo->save($id, ['__meta' => ['id' => $id]]);

        return $id;
    }
}
