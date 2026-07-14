<?php

namespace Inertia\Tests;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Inertia\Ssr\HttpGateway;
use Inertia\Ssr\SsrErrorType;
use Inertia\Ssr\SsrException;
use Inertia\Ssr\SsrRenderFailed;

class HttpGatewayTest extends TestCase
{
    protected HttpGateway $gateway;

    protected string $renderUrl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = app(HttpGateway::class);
        $this->renderUrl = $this->gateway->getProductionUrl('/render');

        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        $this->removeHotFile();

        parent::tearDown();
    }

    protected function createHotFile(string $url = 'http://localhost:5173'): void
    {
        file_put_contents(public_path('hot'), $url);
    }

    protected function removeHotFile(): void
    {
        $hotFile = public_path('hot');
        if (file_exists($hotFile)) {
            unlink($hotFile);
        }
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

    /**
     * Create a new connection exception for use during stubbing.
     *
     * This is copied over from Laravel's Http::failedConnection() helper
     * method, which is only available in Laravel 11.32.0 and later.
     */
    private static function rejectionForFailedConnection(): PromiseInterface
    {
        return Create::rejectionFor(
            new ConnectException('Connection refused', new Request('GET', '/'))
        );
    }

    public function test_health_check_the_ssr_server(): void
    {
        Http::fake([
            $this->gateway->getProductionUrl('/health') => Http::sequence()
                ->push(status: 200)
                ->push(status: 500)
                ->pushResponse(self::rejectionForFailedConnection()),
        ]);

        $this->assertTrue($this->gateway->isHealthy());
        $this->assertFalse($this->gateway->isHealthy());
        $this->assertFalse($this->gateway->isHealthy());
    }

    public function test_it_uses_vite_hot_url_when_running_hot(): void
    {
        config(['inertia.ssr.enabled' => true]);

        $this->createHotFile('http://localhost:5173');

        Http::fake([
            'http://localhost:5173/__inertia_ssr' => Http::response(json_encode([
                'head' => ['<title>Hot SSR</title>'],
                'body' => '<div id="app">Hot Response</div>',
            ])),
        ]);

        $response = $this->gateway->dispatch(['page' => self::EXAMPLE_PAGE_OBJECT]);

        $this->assertNotNull($response);
        $this->assertEquals('<title>Hot SSR</title>', $response->head);
        $this->assertEquals('<div id="app">Hot Response</div>', $response->body);
    }

    public function test_it_uses_vite_hot_url_even_when_bundle_file_exists(): void
    {
        config([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.bundle' => __DIR__.'/Stubs/ssr-bundle.js',
        ]);

        $this->createHotFile('http://localhost:5173');

        Http::fake([
            'http://localhost:5173/__inertia_ssr' => Http::response(json_encode([
                'head' => ['<title>Hot SSR</title>'],
                'body' => '<div id="app">Hot Response</div>',
            ])),
            $this->renderUrl => Http::response(json_encode([
                'head' => ['<title>Production SSR</title>'],
                'body' => '<div id="app">Production Response</div>',
            ])),
        ]);

        $response = $this->gateway->dispatch(['page' => self::EXAMPLE_PAGE_OBJECT]);

        $this->assertNotNull($response);
        $this->assertEquals('<title>Hot SSR</title>', $response->head);
        $this->assertEquals('<div id="app">Hot Response</div>', $response->body);
    }

    public function test_it_uses_configured_dev_url_when_running_hot(): void
    {
        config([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.hot_url' => 'http://custom-hot-server:5173',
        ]);

        $this->createHotFile('http://localhost:5173');

        Http::fake([
            'http://custom-hot-server:5173/__inertia_ssr' => Http::response(json_encode([
                    'head' => ['<title>Custom Hot SSR</title>'],
                    'body' => '<div id="app">Custom Hot Response</div>',
            ])),
        ]);

        $response = $this->gateway->dispatch(['page' => self::EXAMPLE_PAGE_OBJECT]);
        
        $this->assertNotNull($response);
        $this->assertEquals('<title>Custom Hot SSR</title>', $response->head);
        $this->assertEquals('<div id="app">Custom Hot Response</div>', $response->body);
    }

    public function test_it_returns_null_when_path_is_excluded_from_ssr(): void
    {
        config([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.bundle' => __DIR__.'/Stubs/ssr-bundle.js',
        ]);

        $this->gateway->except(['admin/*']);

        $this->get('/admin/dashboard');

        $this->assertNull($this->gateway->dispatch(['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function test_it_dispatches_when_path_is_not_excluded_from_ssr(): void
    {
        config([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.bundle' => __DIR__.'/Stubs/ssr-bundle.js',
        ]);

        Http::fake([
            $this->renderUrl => Http::response(json_encode([
                'head' => ['<title>SSR Test</title>'],
                'body' => '<div id="app">SSR Response</div>',
            ])),
        ]);

        $this->gateway->except(['admin/*']);

        $this->get('/users');

        $this->assertNotNull($this->gateway->dispatch(['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function test_it_returns_null_when_full_url_is_excluded_from_ssr(): void
    {
        config([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.bundle' => __DIR__.'/Stubs/ssr-bundle.js',
        ]);

        $this->gateway->except(['http://localhost/admin/*']);

        $this->get('/admin/dashboard');

        $this->assertNull($this->gateway->dispatch(['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function test_except_accepts_string(): void
    {
        config([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.bundle' => __DIR__.'/Stubs/ssr-bundle.js',
        ]);

        $this->gateway->except('admin/*');

        $this->get('/admin/dashboard');

        $this->assertNull($this->gateway->dispatch(['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function test_production_url_strips_trailing_slash(): void
    {
        config(['inertia.ssr.url' => 'http://127.0.0.1:13714/']);

        $gateway = app(HttpGateway::class);

        $this->assertEquals('http://127.0.0.1:13714/render', $gateway->getProductionUrl('/render'));
    }

    public function test_except_can_be_called_multiple_times(): void
    {
        config([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.bundle' => __DIR__.'/Stubs/ssr-bundle.js',
        ]);

        $this->gateway->except('admin/*');
        $this->gateway->except(['nova/*', 'filament/*']);

        $this->get('/nova/resources');

        $this->assertNull($this->gateway->dispatch(['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function test_it_dispatches_event_when_ssr_fails(): void
    {
        Event::fake([SsrRenderFailed::class]);

        config([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.bundle' => __DIR__.'/Stubs/ssr-bundle.js',
        ]);

        Http::fake([
            $this->renderUrl => Http::response(json_encode([
                'error' => 'window is not defined',
                'type' => 'browser-api',
                'hint' => 'Wrap in lifecycle hook',
                'browserApi' => 'window',
            ]), 500),
        ]);

        $this->assertNull($this->gateway->dispatch(self::EXAMPLE_PAGE_OBJECT));

        Event::assertDispatched(SsrRenderFailed::class, function (SsrRenderFailed $event) {
            return $event->error === 'window is not defined'
                && $event->type === SsrErrorType::BrowserApi
                && $event->hint === 'Wrap in lifecycle hook'
                && $event->browserApi === 'window'
                && $event->component() === 'Foo/Bar';
        });
    }

    public function test_it_handles_connection_errors_gracefully(): void
    {
        Event::fake([SsrRenderFailed::class]);

        config([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.bundle' => __DIR__.'/Stubs/ssr-bundle.js',
        ]);

        Http::fake([
            $this->renderUrl => self::rejectionForFailedConnection(),
        ]);

        $this->assertNull($this->gateway->dispatch(self::EXAMPLE_PAGE_OBJECT));

        Event::assertDispatched(SsrRenderFailed::class, function (SsrRenderFailed $event) {
            return $event->type === SsrErrorType::Connection
                && str_contains($event->error, 'Connection refused');
        });
    }

    public function test_it_throws_exception_when_throw_on_error_is_enabled(): void
    {
        Event::fake([SsrRenderFailed::class]);

        config([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.bundle' => __DIR__.'/Stubs/ssr-bundle.js',
            'inertia.ssr.throw_on_error' => true,
        ]);

        Http::fake([
            $this->renderUrl => Http::response(json_encode([
                'error' => 'window is not defined',
                'type' => 'browser-api',
                'hint' => 'Wrap in lifecycle hook',
                'browserApi' => 'window',
                'sourceLocation' => 'resources/js/Pages/Dashboard.vue:10:5',
            ]), 500),
        ]);

        $this->expectException(SsrException::class);
        $this->expectExceptionMessage('SSR render failed for component [Foo/Bar]: window is not defined');

        $this->gateway->dispatch(self::EXAMPLE_PAGE_OBJECT);
    }

    public function test_ssr_exception_contains_error_details(): void
    {
        Event::fake([SsrRenderFailed::class]);

        config([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.bundle' => __DIR__.'/Stubs/ssr-bundle.js',
            'inertia.ssr.throw_on_error' => true,
        ]);

        Http::fake([
            $this->renderUrl => Http::response(json_encode([
                'error' => 'window is not defined',
                'type' => 'browser-api',
                'hint' => 'Wrap in lifecycle hook',
                'browserApi' => 'window',
                'sourceLocation' => 'resources/js/Pages/Dashboard.vue:10:5',
            ]), 500),
        ]);

        try {
            $this->gateway->dispatch(self::EXAMPLE_PAGE_OBJECT);
            $this->fail('Expected SsrException was not thrown');
        } catch (SsrException $e) {
            $this->assertEquals('Foo/Bar', $e->component());
            $this->assertSame(SsrErrorType::BrowserApi, $e->type());
            $this->assertEquals('Wrap in lifecycle hook', $e->hint());
            $this->assertEquals('resources/js/Pages/Dashboard.vue:10:5', $e->sourceLocation());
            $this->assertStringContainsString('at resources/js/Pages/Dashboard.vue:10:5', $e->getMessage());
        }
    }

    public function test_it_throws_exception_on_connection_error_when_throw_on_error_is_enabled(): void
    {
        Event::fake([SsrRenderFailed::class]);

        config([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.bundle' => __DIR__.'/Stubs/ssr-bundle.js',
            'inertia.ssr.throw_on_error' => true,
        ]);

        Http::fake([
            $this->renderUrl => self::rejectionForFailedConnection(),
        ]);

        $this->expectException(SsrException::class);
        $this->expectExceptionMessage('Connection refused');

        $this->gateway->dispatch(self::EXAMPLE_PAGE_OBJECT);
    }

    public function test_it_returns_null_when_disabled_with_boolean(): void
    {
        config([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.bundle' => __DIR__.'/Stubs/ssr-bundle.js',
        ]);

        $this->gateway->disable(true);

        $this->assertNull($this->gateway->dispatch(['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function test_it_returns_null_when_disabled_with_closure(): void
    {
        config([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.bundle' => __DIR__.'/Stubs/ssr-bundle.js',
        ]);

        $this->gateway->disable(fn () => true);

        $this->assertNull($this->gateway->dispatch(['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function test_disable_when_takes_precedence_over_config(): void
    {
        config([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.bundle' => __DIR__.'/Stubs/ssr-bundle.js',
        ]);

        $this->gateway->disable(false);

        Http::fake([
            $this->renderUrl => Http::response(json_encode([
                'head' => ['<title>SSR Test</title>'],
                'body' => '<div id="app">SSR Response</div>',
            ])),
        ]);

        $this->assertNotNull($this->gateway->dispatch(['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function test_it_does_not_throw_exception_when_throw_on_error_is_disabled(): void
    {
        Event::fake([SsrRenderFailed::class]);

        config([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.bundle' => __DIR__.'/Stubs/ssr-bundle.js',
            'inertia.ssr.throw_on_error' => false,
        ]);

        Http::fake([
            $this->renderUrl => Http::response(json_encode([
                'error' => 'window is not defined',
                'type' => 'browser-api',
            ]), 500),
        ]);

        $this->assertNull($this->gateway->dispatch(self::EXAMPLE_PAGE_OBJECT));
    }
}
