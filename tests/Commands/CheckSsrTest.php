<?php

namespace Inertia\Tests;

use Inertia\Ssr\Gateway;
use Inertia\Ssr\HttpGateway;

class CheckSsrTest extends TestCase
{
    public function test_success_on_healthy_ssr_server(): void
    {
        $this->mock(HttpGateway::class, fn ($mock) => $mock
            ->shouldReceive('isHealthy')
            ->andReturnTrue()
            ->getMock()
        );

        $this->artisan('inertia:check-ssr')
            ->expectsOutput('Inertia SSR server is running.')
            ->assertExitCode(0);
    }

    public function test_failure_on_unhealthy_ssr_server(): void
    {
        $this->mock(HttpGateway::class, fn ($mock) => $mock
            ->shouldReceive('isHealthy')
            ->andReturnFalse()
            ->getMock()
        );

        $this->artisan('inertia:check-ssr')
            ->expectsOutput('Inertia SSR server is not running.')
            ->assertExitCode(1);
    }

    public function test_failure_on_unsupported_gateway(): void
    {
        $this->mock(Gateway::class);

        $this->artisan('inertia:check-ssr')
            ->expectsOutput('The SSR gateway does not support health checks.')
            ->assertExitCode(1);
    }
}
