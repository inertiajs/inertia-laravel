<?php

namespace Inertia\Tests\Commands;

use Inertia\Tests\TestCase;

class StopSsrTest extends TestCase
{
    protected ?string $directory = null;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('inertia.ssr.url', 'http://127.0.0.1:1');
    }

    protected function tearDown(): void
    {
        if ($this->directory) {
            unlink($this->directory.'/shutdown');
            rmdir($this->directory);
        }

        parent::tearDown();
    }

    protected function fakeResponse(string $body): void
    {
        $this->directory = sys_get_temp_dir().'/inertia-stop-ssr-'.uniqid();

        mkdir($this->directory);
        file_put_contents($this->directory.'/shutdown', $body);

        config()->set('inertia.ssr.url', 'file://'.$this->directory);
    }

    public function test_failure_when_the_ssr_server_is_not_running(): void
    {
        $this->artisan('inertia:stop-ssr')
            ->expectsOutput('Unable to connect to Inertia SSR server.')
            ->assertExitCode(1);
    }

    public function test_success_when_the_ssr_server_is_not_running_and_the_graceful_option_is_used(): void
    {
        $this->artisan('inertia:stop-ssr', ['--graceful' => true])
            ->expectsOutput('Inertia SSR server is not running.')
            ->assertExitCode(0);
    }

    public function test_failure_when_another_service_responds_on_the_ssr_url(): void
    {
        $this->fakeResponse('Hello from another service');

        $this->artisan('inertia:stop-ssr')
            ->expectsOutput('Unable to connect to Inertia SSR server.')
            ->assertExitCode(1);
    }

    public function test_failure_when_another_service_responds_on_the_ssr_url_and_the_graceful_option_is_used(): void
    {
        $this->fakeResponse('Hello from another service');

        $this->artisan('inertia:stop-ssr', ['--graceful' => true])
            ->expectsOutput('Unable to connect to Inertia SSR server.')
            ->assertExitCode(1);
    }

    public function test_the_response_body_is_not_printed_to_the_console(): void
    {
        $this->fakeResponse('Hello from another service');

        $this->expectOutputString('');

        $this->artisan('inertia:stop-ssr')->run();
    }
}
