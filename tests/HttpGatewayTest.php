<?php

namespace Inertia\Tests;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Create;
use Illuminate\Support\Facades\Http;
use Inertia\Ssr\HttpGateway;

class HttpGatewayTest extends TestCase
{
    protected HttpGateway $gateway;

    protected string $renderUrl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new HttpGateway;
        $this->renderUrl = $this->gateway->getUrl('render');

        Http::preventStrayRequests();
    }

    public function test_it_returns_null_when_ssr_is_disabled(): void
    {
        config([
            'inertia.ssr.enabled' => false,
            'inertia.ssr.bundle' => __DIR__.'/Stubs/ssr-bundle.js',
        ]);

        $this->assertNull($this->gateway->dispatch(['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function test_it_returns_null_when_no_bundle_file_is_detected(): void
    {
        config([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.bundle' => null,
        ]);

        $this->assertNull($this->gateway->dispatch(['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function test_it_uses_the_configured_http_url_when_the_bundle_file_is_detected(): void
    {
        config([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.bundle' => __DIR__.'/Stubs/ssr-bundle.js',
        ]);

        Http::fake([
            $this->renderUrl => Http::response(json_encode([
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

    public function test_it_uses_the_configured_http_url_when_bundle_file_detection_is_disabled(): void
    {
        config([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.ensure_bundle_exists' => false,
            'inertia.ssr.bundle' => null,
        ]);

        Http::fake([
            $this->renderUrl => Http::response(json_encode([
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

    public function test_it_returns_null_when_the_http_request_fails(): void
    {
        config([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.bundle' => __DIR__.'/Stubs/ssr-bundle.js',
        ]);

        Http::fake([
            $this->renderUrl => Http::response(null, 500),
        ]);

        $this->assertNull($this->gateway->dispatch(['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function test_it_returns_null_when_invalid_json_is_returned(): void
    {
        config([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.bundle' => __DIR__.'/Stubs/ssr-bundle.js',
        ]);

        Http::fake([
            $this->renderUrl => Http::response('invalid json'),
        ]);

        $this->assertNull($this->gateway->dispatch(['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function test_health_check_the_ssr_server(): void
    {
        Http::fake([
            $this->gateway->getUrl('health') => Http::sequence()
                ->push(status: 200)
                ->push(status: 500)
                ->pushResponse(
                    fn ($request) => Create::rejectionFor(new ConnectException(
                        "cURL error 6: Could not resolve host: {$request->toPsrRequest()->getUri()->getHost()} (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for {$request->toPsrRequest()->getUri()}.",
                        $request->toPsrRequest(),
                    ))
                ),
        ]);

        $this->assertTrue($this->gateway->isHealthy());
        $this->assertFalse($this->gateway->isHealthy());
        $this->assertFalse($this->gateway->isHealthy());
    }
}
