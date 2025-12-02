<?php

namespace Inertia\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Illuminate\View\Compilers\BladeCompiler;
use Inertia\Directive;
use Inertia\Ssr\Gateway;
use Inertia\Tests\Stubs\FakeGateway;
use Mockery as m;

class DirectiveTest extends TestCase
{
    /**
     * @var Filesystem|m\MockInterface
     */
    private $filesystem;

    /**
     * @var BladeCompiler
     */
    protected $compiler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(Gateway::class, FakeGateway::class);
        $this->filesystem = m::mock(Filesystem::class);

        /** @var Filesystem $filesystem */
        $filesystem = $this->filesystem;
        $this->compiler = new BladeCompiler($filesystem, __DIR__.'/cache/views');
        $this->compiler->directive('inertia', [Directive::class, 'compile']);
        $this->compiler->directive('inertiaHead', [Directive::class, 'compileHead']);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function renderView(string $contents, array $data = []): string
    {
        return Blade::render($contents, $data, true);
    }

    public function test_inertia_directive_renders_the_root_element(): void
    {
        Config::set(['inertia.ssr.enabled' => false]);

        $html = '<div id="app" data-page="{&quot;component&quot;:&quot;Foo\/Bar&quot;,&quot;props&quot;:{&quot;foo&quot;:&quot;bar&quot;},&quot;url&quot;:&quot;\/test&quot;,&quot;version&quot;:&quot;&quot;,&quot;encryptHistory&quot;:false,&quot;clearHistory&quot;:false}"></div>';

        $this->assertSame($html, $this->renderView('@inertia', ['page' => self::EXAMPLE_PAGE_OBJECT]));
        $this->assertSame($html, $this->renderView('@inertia()', ['page' => self::EXAMPLE_PAGE_OBJECT]));
        $this->assertSame($html, $this->renderView('@inertia("")', ['page' => self::EXAMPLE_PAGE_OBJECT]));
        $this->assertSame($html, $this->renderView("@inertia('')", ['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function test_inertia_directive_renders_the_root_element_and_script_element(): void
    {
        Config::set([
            'inertia.ssr.enabled' => false,
            'inertia.use_script_element_for_initial_page' => true,
        ]);

        $html = '<script data-page="app" type="application/json">{"component":"Foo\/Bar","props":{"foo":"bar"},"url":"\/test","version":"","encryptHistory":false,"clearHistory":false}</script><div id="app"></div>';

        $this->assertSame($html, $this->renderView('@inertia', ['page' => self::EXAMPLE_PAGE_OBJECT]));
        $this->assertSame($html, $this->renderView('@inertia()', ['page' => self::EXAMPLE_PAGE_OBJECT]));
        $this->assertSame($html, $this->renderView('@inertia("")', ['page' => self::EXAMPLE_PAGE_OBJECT]));
        $this->assertSame($html, $this->renderView("@inertia('')", ['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function test_inertia_directive_renders_server_side_rendered_content_when_enabled(): void
    {
        Config::set(['inertia.ssr.enabled' => true]);

        $this->assertSame(
            '<p>This is some example SSR content</p>',
            $this->renderView('@inertia', ['page' => self::EXAMPLE_PAGE_OBJECT])
        );
    }

    public function test_inertia_directive_can_use_a_different_root_element_id(): void
    {
        Config::set(['inertia.ssr.enabled' => false]);

        $html = '<div id="foo" data-page="{&quot;component&quot;:&quot;Foo\/Bar&quot;,&quot;props&quot;:{&quot;foo&quot;:&quot;bar&quot;},&quot;url&quot;:&quot;\/test&quot;,&quot;version&quot;:&quot;&quot;,&quot;encryptHistory&quot;:false,&quot;clearHistory&quot;:false}"></div>';

        $this->assertSame($html, $this->renderView('@inertia(foo)', ['page' => self::EXAMPLE_PAGE_OBJECT]));
        $this->assertSame($html, $this->renderView("@inertia('foo')", ['page' => self::EXAMPLE_PAGE_OBJECT]));
        $this->assertSame($html, $this->renderView('@inertia("foo")', ['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function test_inertia_directive_can_use_a_different_root_element_id_when_using_script_element(): void
    {
        Config::set([
            'inertia.ssr.enabled' => false,
            'inertia.use_script_element_for_initial_page' => true,
        ]);

        $html = '<script data-page="foo" type="application/json">{"component":"Foo\/Bar","props":{"foo":"bar"},"url":"\/test","version":"","encryptHistory":false,"clearHistory":false}</script><div id="foo"></div>';

        $this->assertSame($html, $this->renderView('@inertia(foo)', ['page' => self::EXAMPLE_PAGE_OBJECT]));
        $this->assertSame($html, $this->renderView("@inertia('foo')", ['page' => self::EXAMPLE_PAGE_OBJECT]));
        $this->assertSame($html, $this->renderView('@inertia("foo")', ['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function test_inertia_head_directive_renders_nothing(): void
    {
        Config::set(['inertia.ssr.enabled' => false]);

        $this->assertEmpty($this->renderView('@inertiaHead', ['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function test_inertia_head_directive_renders_server_side_rendered_head_elements_when_enabled(): void
    {
        Config::set(['inertia.ssr.enabled' => true]);

        $this->assertSame(
            "<meta charset=\"UTF-8\" />\n<title inertia>Example SSR Title</title>\n",
            $this->renderView('@inertiaHead', ['page' => self::EXAMPLE_PAGE_OBJECT])
        );
    }

    public function test_the_server_side_rendering_request_is_dispatched_only_once_per_request(): void
    {
        Config::set(['inertia.ssr.enabled' => true]);
        $this->app->instance(Gateway::class, $gateway = new FakeGateway);

        $view = "<!DOCTYPE html>\n<html>\n<head>\n@inertiaHead\n</head>\n<body>\n@inertia\n</body>\n</html>";
        $expected = "<!DOCTYPE html>\n<html>\n<head>\n<meta charset=\"UTF-8\" />\n<title inertia>Example SSR Title</title>\n</head>\n<body>\n<p>This is some example SSR content</p></body>\n</html>";

        $this->assertSame(
            $expected,
            $this->renderView($view, ['page' => self::EXAMPLE_PAGE_OBJECT])
        );

        $this->assertSame(1, $gateway->times);
    }
}
