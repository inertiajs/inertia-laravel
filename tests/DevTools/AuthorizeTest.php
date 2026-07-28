<?php

namespace Inertia\Tests\DevTools;

use Illuminate\Support\Str;
use Inertia\Tests\TestCase;

class AuthorizeTest extends TestCase
{
    use InteractsWithDevToolsStorage;

    protected function defineEnvironment($app): void
    {
        // The entry endpoints run the `web` middleware group, which encrypts cookies.
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('inertia.devtools.enabled', true);
        $app['config']->set('inertia.devtools.gate', null);
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

    public function test_the_local_environment_is_allowed_without_a_gate(): void
    {
        $this->environment('local');

        $this->getJson('/_inertia/devtools/entries')->assertOk();
        $this->getJson('/_inertia/devtools/entries/'.$this->savedEntryId())->assertOk();
    }

    public function test_other_environments_are_denied_without_a_gate(): void
    {
        $this->environment('production');

        $this->getJson('/_inertia/devtools/entries')->assertForbidden();
        $this->getJson('/_inertia/devtools/entries/'.$this->savedEntryId())->assertForbidden();
    }

    public function test_polling_the_entries_endpoint_does_not_become_the_previous_url(): void
    {
        $this->environment('local');

        $this->getJson('/_inertia/devtools/entries')->assertOk();

        // The extension polls these endpoints in the background. A recorded previous URL
        // would become the target of the app's next `back()` redirect.
        $this->assertNull($this->app['session']->previousUrl());
    }

    protected function environment(string $environment): void
    {
        $this->app['env'] = $environment;
    }

    protected function savedEntryId(): string
    {
        $id = (string) Str::ulid();

        $this->repo->save($id, ['__meta' => ['id' => $id]]);

        return $id;
    }
}
