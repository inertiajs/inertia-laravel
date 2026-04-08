<?php

namespace Inertia\Tests;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Inertia\Ssr\Gateway;
use Inertia\Ssr\SsrState;
use Inertia\Tests\Stubs\FakeGateway;

class ComponentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(Gateway::class, FakeGateway::class);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function renderView(string $contents, array $data = []): string
    {
        app(SsrState::class)->setPage($data['page'] ?? []);

        return Blade::render($contents, $data, true);
    }

    public function test_head_component_renders_fallback_slot_when_ssr_is_disabled(): void
    {
        Config::set(['inertia.ssr.enabled' => false]);

        $view = '<x-inertia::head><title>Fallback Title</title></x-inertia::head>';

        $this->assertStringContainsString(
            '<title>Fallback Title</title>',
            $this->renderView($view, ['page' => self::EXAMPLE_PAGE_OBJECT])
        );
    }

    public function test_head_component_renders_ssr_head_when_ssr_is_enabled(): void
    {
        Config::set(['inertia.ssr.enabled' => true]);

        $view = '<x-inertia::head><title>Fallback Title</title></x-inertia::head>';
        $rendered = $this->renderView($view, ['page' => self::EXAMPLE_PAGE_OBJECT]);

        $this->assertStringContainsString('<title inertia>Example SSR Title</title>', $rendered);
        $this->assertStringNotContainsString('<title>Fallback Title</title>', $rendered);
    }

    public function test_app_component_renders_client_side_div_when_ssr_is_disabled(): void
    {
        Config::set(['inertia.ssr.enabled' => false]);

        $view = '<x-inertia::app />';
        $rendered = $this->renderView($view, ['page' => self::EXAMPLE_PAGE_OBJECT]);

        $this->assertStringContainsString('<div id="app"></div>', $rendered);
        $this->assertStringContainsString('data-page="app"', $rendered);
    }

    public function test_app_component_renders_ssr_body_when_ssr_is_enabled(): void
    {
        Config::set(['inertia.ssr.enabled' => true]);

        $view = '<x-inertia::app />';

        $this->assertSame(
            '<p>This is some example SSR content</p>',
            trim($this->renderView($view, ['page' => self::EXAMPLE_PAGE_OBJECT]))
        );
    }

    public function test_app_component_accepts_custom_id(): void
    {
        Config::set(['inertia.ssr.enabled' => false]);

        $view = '<x-inertia::app id="custom" />';
        $rendered = $this->renderView($view, ['page' => self::EXAMPLE_PAGE_OBJECT]);

        $this->assertStringContainsString('<div id="custom"></div>', $rendered);
        $this->assertStringContainsString('data-page="custom"', $rendered);
    }

    public function test_ssr_is_only_dispatched_once_with_components(): void
    {
        Config::set(['inertia.ssr.enabled' => true]);
        $this->app->instance(Gateway::class, $gateway = new FakeGateway);

        $view = '<x-inertia::head><title>Fallback</title></x-inertia::head><x-inertia::app />';
        $this->renderView($view, ['page' => self::EXAMPLE_PAGE_OBJECT]);

        $this->assertSame(1, $gateway->times);
    }

    public function test_app_component_matches_directive_output_when_ssr_is_disabled(): void
    {
        Config::set(['inertia.ssr.enabled' => false]);

        $directive = $this->renderView('@inertia', ['page' => self::EXAMPLE_PAGE_OBJECT]);

        $this->app->forgetScopedInstances();

        $component = trim($this->renderView('<x-inertia::app />', ['page' => self::EXAMPLE_PAGE_OBJECT]));

        $this->assertSame($directive, $component);
    }

    public function test_app_component_matches_directive_output_when_ssr_is_enabled(): void
    {
        Config::set(['inertia.ssr.enabled' => true]);

        $directive = $this->renderView('@inertia', ['page' => self::EXAMPLE_PAGE_OBJECT]);

        $this->app->forgetScopedInstances();

        $component = trim($this->renderView('<x-inertia::app />', ['page' => self::EXAMPLE_PAGE_OBJECT]));

        $this->assertSame($directive, $component);
    }

    public function test_app_component_with_custom_id_matches_directive_output(): void
    {
        Config::set(['inertia.ssr.enabled' => false]);

        $directive = $this->renderView('@inertia("foo")', ['page' => self::EXAMPLE_PAGE_OBJECT]);

        $this->app->forgetScopedInstances();

        $component = trim($this->renderView('<x-inertia::app id="foo" />', ['page' => self::EXAMPLE_PAGE_OBJECT]));

        $this->assertSame($directive, $component);
    }

    public function test_head_component_without_slot_matches_directive_output_when_ssr_is_disabled(): void
    {
        Config::set(['inertia.ssr.enabled' => false]);

        $directive = $this->renderView('@inertiaHead', ['page' => self::EXAMPLE_PAGE_OBJECT]);

        $this->app->forgetScopedInstances();

        $component = trim($this->renderView('<x-inertia::head />', ['page' => self::EXAMPLE_PAGE_OBJECT]));

        $this->assertSame($directive, $component);
    }

    public function test_head_component_without_slot_matches_directive_output_when_ssr_is_enabled(): void
    {
        Config::set(['inertia.ssr.enabled' => true]);

        $directive = trim($this->renderView('@inertiaHead', ['page' => self::EXAMPLE_PAGE_OBJECT]));

        $this->app->forgetScopedInstances();

        $component = trim($this->renderView('<x-inertia::head />', ['page' => self::EXAMPLE_PAGE_OBJECT]));

        $this->assertSame($directive, $component);
    }

    public function test_components_do_not_create_cached_view_files_per_request(): void
    {
        Config::set(['inertia.ssr.enabled' => true]);

        $viewCachePath = $this->app['config']['view.compiled'];
        $view = '<x-inertia::head><title>Fallback</title></x-inertia::head><x-inertia::app />';

        $this->renderView($view, ['page' => self::EXAMPLE_PAGE_OBJECT]);
        $cachedViews = glob($viewCachePath.'/*.php');

        $this->app->forgetScopedInstances();

        $this->renderView($view, ['page' => ['component' => 'Different', 'props' => ['foo' => 'bar']]]);
        $this->assertSame($cachedViews, glob($viewCachePath.'/*.php'));
    }

    public function test_ssr_state_is_scoped_and_does_not_leak_between_requests(): void
    {
        Config::set(['inertia.ssr.enabled' => true]);

        $state1 = app(SsrState::class);
        $state1->setPage(self::EXAMPLE_PAGE_OBJECT);
        $state1->dispatch();

        $this->assertNotNull($state1->response);

        // Simulate Octane request boundary by flushing scoped instances
        $this->app->forgetScopedInstances();

        $state2 = app(SsrState::class);

        $this->assertNotSame($state1, $state2);
        $this->assertNull($state2->response);
    }
}
