<?php

namespace Inertia\Tests;

class StopSsrTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('inertia.ssr.url', 'http://127.0.0.1:1');
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
}
