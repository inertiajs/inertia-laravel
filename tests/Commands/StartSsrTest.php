<?php

namespace Inertia\Tests\Commands;

use Inertia\Ssr\HttpGateway;
use Inertia\Tests\TestCase;
use Mockery;

class StartSsrTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('inertia.ssr.enabled', true);
        config()->set('inertia.ssr.bundle', __DIR__.'/../Stubs/ssr-bundle.js');
    }

    public function test_stops_a_running_server_before_starting(): void
    {
        $gateway = Mockery::mock(HttpGateway::class);
        $gateway->shouldReceive('getUrl')->once()->with('/shutdown')->andReturn('http://127.0.0.1:1/shutdown');
        $this->app->instance(HttpGateway::class, $gateway);

        $this->artisan('inertia:start-ssr')->assertExitCode(0);
    }

    public function test_no_stop_option_leaves_a_running_server_alone(): void
    {
        $gateway = Mockery::mock(HttpGateway::class);
        $gateway->shouldNotReceive('getUrl');
        $this->app->instance(HttpGateway::class, $gateway);

        $this->artisan('inertia:start-ssr', ['--no-stop' => true])->assertExitCode(0);
    }
}
