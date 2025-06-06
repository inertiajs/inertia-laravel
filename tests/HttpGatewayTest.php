<?php

namespace Inertia\Tests;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Vite;
use Inertia\Ssr\HttpGateway;

class HttpGatewayTest extends TestCase
{
    protected HttpGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new HttpGateway;

        Http::preventStrayRequests();
    }

    public function test_it_returns_null_when_ssr_is_disabled()
    {
        config([
            'inertia.ssr.enabled' => false,
            'inertia.ssr.bundle' => __DIR__.'/Stubs/bundle.js',
        ]);

        Vite::shouldReceive('isRunningHot')->never();

        $this->assertNull($this->gateway->dispatch(['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function test_it_returns_null_when_no_bundle_file_is_detected_and_vite_is_not_running()
    {
        config([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.bundle' => null,
        ]);

        Vite::shouldReceive('isRunningHot')->andReturn(false);

        $this->assertNull($this->gateway->dispatch(['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function test_it_uses_the_configured_http_url_when_the_bundle_file_is_detected()
    {
        config([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.bundle' => __DIR__.'/Stubs/bundle.js',
        ]);

        Vite::shouldReceive('isRunningHot')->andReturn(false);

        Http::fake([
            $this->gateway->getHttpUrl() => Http::response(json_encode([
                'head' => ['<title>SSR Test</title>', '<style></style>'],
                'body' => '<div id="app">SSR Response</div>',
            ])),
        ]);

        $this->assertNotNull(
            $response = $this->gateway->dispatch(['page' => self::EXAMPLE_PAGE_OBJECT])
        );

        $this->assertEquals("<title>SSR Test</title>\n<style></style>", $response->head);
        $this->assertEquals('<div id="app">SSR Response</div>', $response->body);
    }

    public function test_it_uses_the_vite_asset_when_it_is_running_hot_even_if_a_bundle_file_is_present()
    {
        config([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.bundle' => __DIR__.'/Stubs/bundle.js',
        ]);

        Vite::shouldReceive('isRunningHot')->andReturn(true);
        Vite::shouldReceive('asset')->with('render')->andReturn($viteUrl = 'http://localhost:3000/some-url');

        Http::fake([
            $viteUrl => Http::response(json_encode([
                'head' => ['<title>SSR Test</title>', '<style></style>'],
                'body' => '<div id="app">SSR Response</div>',
            ])),
        ]);

        $this->assertNotNull(
            $response = $this->gateway->dispatch(['page' => self::EXAMPLE_PAGE_OBJECT])
        );

        $this->assertEquals("<title>SSR Test</title>\n<style></style>", $response->head);
        $this->assertEquals('<div id="app">SSR Response</div>', $response->body);
    }

    public function test_it_returns_null_when_the_http_request_fails()
    {
        config([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.bundle' => __DIR__.'/Stubs/bundle.js',
        ]);

        Vite::shouldReceive('isRunningHot')->andReturn(false);

        Http::fake([
            $this->gateway->getHttpUrl() => Http::response(null, 500),
        ]);

        $this->assertNull($this->gateway->dispatch(['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function test_it_returns_null_when_invalid_json_is_returned()
    {
        config([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.bundle' => __DIR__.'/Stubs/bundle.js',
        ]);

        Vite::shouldReceive('isRunningHot')->andReturn(false);

        Http::fake([
            $this->gateway->getHttpUrl() => Http::response('invalid json'),
        ]);

        $this->assertNull($this->gateway->dispatch(['page' => self::EXAMPLE_PAGE_OBJECT]));
    }
}
