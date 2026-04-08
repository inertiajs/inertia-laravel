<?php

namespace Inertia\Tests\Commands;

use Inertia\Tests\TestCase;
use Symfony\Component\Process\Process;

class StartSsrTest extends TestCase
{
    /** @var list<string>|null */
    protected ?array $processCommand = null;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('inertia.ssr.enabled', true);
        config()->set('inertia.ssr.bundle', __FILE__);
    }

    protected function fakeProcess(): void
    {
        $this->app->bind(Process::class, function ($app, $params) {
            $this->processCommand = $params['command'];

            return new Process(['true']);
        });
    }

    public function test_error_when_ssr_is_disabled(): void
    {
        config()->set('inertia.ssr.enabled', false);

        $this->artisan('inertia:start-ssr')
            ->expectsOutput('Inertia SSR is not enabled. Enable it via the `inertia.ssr.enabled` config option.')
            ->assertExitCode(1);
    }

    public function test_error_when_configured_bundle_not_found(): void
    {
        config()->set('inertia.ssr.bundle', '/nonexistent/path/ssr.mjs');

        $this->artisan('inertia:start-ssr')
            ->expectsOutput('Inertia SSR bundle not found at the configured path: "/nonexistent/path/ssr.mjs"')
            ->assertExitCode(1);
    }

    public function test_error_when_no_bundle_configured_and_detection_fails(): void
    {
        config()->set('inertia.ssr.bundle', null);

        $this->artisan('inertia:start-ssr')
            ->expectsOutput('Inertia SSR bundle not found. Set the correct Inertia SSR bundle path in your `inertia.ssr.bundle` config.')
            ->assertExitCode(1);
    }

    public function test_bundle_is_auto_detected_when_not_configured(): void
    {
        $this->fakeProcess();
        config()->set('inertia.ssr.bundle', null);

        $bundlePath = base_path('bootstrap/ssr/ssr.mjs');
        @mkdir(dirname($bundlePath), recursive: true);
        file_put_contents($bundlePath, '');

        try {
            $this->artisan('inertia:start-ssr')->assertExitCode(0);

            $this->assertSame($bundlePath, $this->processCommand[1]);
        } finally {
            @unlink($bundlePath);
            @rmdir(base_path('bootstrap/ssr'));
        }
    }

    public function test_runtime_defaults_to_node(): void
    {
        $this->fakeProcess();

        $this->artisan('inertia:start-ssr')->assertExitCode(0);

        $this->assertSame('node', $this->processCommand[0]);
    }

    public function test_runtime_can_be_configured(): void
    {
        $this->fakeProcess();
        config()->set('inertia.ssr.runtime', 'bun');

        $this->artisan('inertia:start-ssr')->assertExitCode(0);

        $this->assertSame('bun', $this->processCommand[0]);
    }

    public function test_runtime_can_be_an_absolute_path(): void
    {
        $this->fakeProcess();
        config()->set('inertia.ssr.runtime', '/usr/local/bin/node');

        $this->artisan('inertia:start-ssr')->assertExitCode(0);

        $this->assertSame('/usr/local/bin/node', $this->processCommand[0]);
    }

    public function test_runtime_option_overrides_config(): void
    {
        $this->fakeProcess();
        config()->set('inertia.ssr.runtime', 'bun');

        $this->artisan('inertia:start-ssr', ['--runtime' => '/custom/path/node'])->assertExitCode(0);

        $this->assertSame('/custom/path/node', $this->processCommand[0]);
    }

    public function test_ensure_runtime_exists_fails_when_runtime_not_found(): void
    {
        config()->set('inertia.ssr.ensure_runtime_exists', true);
        config()->set('inertia.ssr.runtime', 'nonexistent-runtime-binary');

        $this->artisan('inertia:start-ssr')
            ->expectsOutput('SSR runtime "nonexistent-runtime-binary" could not be found.')
            ->assertExitCode(1);
    }

    public function test_ensure_runtime_exists_passes_when_runtime_found(): void
    {
        $this->fakeProcess();
        config()->set('inertia.ssr.ensure_runtime_exists', true);
        config()->set('inertia.ssr.runtime', 'php');

        $this->artisan('inertia:start-ssr')->assertExitCode(0);
    }

    public function test_runtime_is_not_checked_by_default(): void
    {
        $this->fakeProcess();
        config()->set('inertia.ssr.runtime', 'nonexistent-runtime-binary');

        $this->artisan('inertia:start-ssr')->assertExitCode(0);

        $this->assertSame('nonexistent-runtime-binary', $this->processCommand[0]);
    }
}
