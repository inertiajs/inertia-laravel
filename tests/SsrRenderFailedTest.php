<?php

namespace Inertia\Tests;

use Inertia\Ssr\SsrErrorType;
use Inertia\Ssr\SsrRenderFailed;

class SsrRenderFailedTest extends TestCase
{
    public function test_it_extracts_component_from_page(): void
    {
        $event = new SsrRenderFailed(
            page: ['component' => 'Dashboard', 'url' => '/dashboard'],
            error: 'Test error',
        );

        $this->assertEquals('Dashboard', $event->component());
    }

    public function test_it_extracts_url_from_page(): void
    {
        $event = new SsrRenderFailed(
            page: ['component' => 'Dashboard', 'url' => '/dashboard'],
            error: 'Test error',
        );

        $this->assertEquals('/dashboard', $event->url());
    }

    public function test_it_stores_source_location(): void
    {
        $event = new SsrRenderFailed(
            page: ['component' => 'Dashboard'],
            error: 'window is not defined',
            sourceLocation: '/path/to/Dashboard.vue:10:5',
        );

        $this->assertEquals('/path/to/Dashboard.vue:10:5', $event->sourceLocation);
    }

    public function test_it_includes_source_location_in_to_array(): void
    {
        $event = new SsrRenderFailed(
            page: ['component' => 'Dashboard', 'url' => '/dashboard'],
            error: 'window is not defined',
            type: SsrErrorType::BrowserApi,
            hint: 'Wrap in lifecycle hook',
            browserApi: 'window',
            sourceLocation: '/path/to/Dashboard.vue:10:5',
        );

        $array = $event->toArray();

        $this->assertEquals('/path/to/Dashboard.vue:10:5', $array['source_location']);
        $this->assertEquals('Dashboard', $array['component']);
        $this->assertEquals('/dashboard', $array['url']);
        $this->assertEquals('window is not defined', $array['error']);
        $this->assertEquals('browser-api', $array['type']);
        $this->assertEquals('Wrap in lifecycle hook', $array['hint']);
        $this->assertEquals('window', $array['browser_api']);
    }

    public function test_to_array_excludes_null_values(): void
    {
        $event = new SsrRenderFailed(
            page: ['component' => 'Dashboard', 'url' => '/dashboard'],
            error: 'Something went wrong',
        );

        $array = $event->toArray();

        $this->assertArrayNotHasKey('hint', $array);
        $this->assertArrayNotHasKey('browser_api', $array);
        $this->assertArrayNotHasKey('source_location', $array);
    }
}
