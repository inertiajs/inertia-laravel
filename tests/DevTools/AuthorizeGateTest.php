<?php

namespace Inertia\Tests\DevTools;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Tests\TestCase;

class AuthorizeGateTest extends TestCase
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
        $this->app['env'] = 'production';
    }

    protected function tearDown(): void
    {
        $this->clearDevToolsStorage();

        parent::tearDown();
    }

    public function test_a_configured_gate_is_authoritative_outside_the_local_environment(): void
    {
        Gate::define('viewInertiaDevtools', fn ($user = null) => true);

        $this->getJson('/_inertia/devtools/entries')->assertOk();
        $this->getJson('/_inertia/devtools/entries/'.$this->savedEntryId())->assertOk();
    }

    public function test_a_failing_gate_denies_access(): void
    {
        Gate::define('viewInertiaDevtools', fn ($user = null) => false);

        $this->getJson('/_inertia/devtools/entries')->assertForbidden();
        $this->getJson('/_inertia/devtools/entries/'.$this->savedEntryId())->assertForbidden();
    }

    public function test_the_local_environment_is_allowed_even_when_the_gate_fails(): void
    {
        $this->app['env'] = 'local';

        // Entries are a local development tool: a gate that fails locally, e.g. because the
        // developer is not signed in, must not lock them out of their own devtools.
        Gate::define('viewInertiaDevtools', fn ($user = null) => false);

        $this->getJson('/_inertia/devtools/entries')->assertOk();
    }

    public function test_the_gate_receives_the_authenticated_user(): void
    {
        Gate::define('viewInertiaDevtools', fn ($user = null) => $user?->getAuthIdentifier() === 42);

        $this->getJson('/_inertia/devtools/entries')->assertForbidden();

        $this->actingAs(new GenericUser(['id' => 42]))
            ->getJson('/_inertia/devtools/entries')
            ->assertOk();
    }

    public function test_the_gate_runs_with_the_session_started(): void
    {
        // Without the session the `web` group starts, a gate that authenticates the
        // user could never allow anyone in.
        Gate::define('viewInertiaDevtools', fn ($user = null) => request()->hasSession());

        $this->getJson('/_inertia/devtools/entries')->assertOk();
    }

    protected function savedEntryId(): string
    {
        $id = (string) Str::ulid();

        $this->repo->save($id, ['__meta' => ['id' => $id]]);

        return $id;
    }
}
