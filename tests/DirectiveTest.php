<?php

namespace Inertia\Tests;

use Throwable;
use Mockery as m;
use Inertia\Directive;
use Inertia\Ssr\Gateway;
use Illuminate\View\View;
use Illuminate\View\Factory;
use Inertia\Tests\Stubs\FakeGateway;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Illuminate\View\Engines\PhpEngine;
use Illuminate\View\Compilers\BladeCompiler;

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

    /**
     * Example Page Objects.
     */
    protected const EXAMPLE_PAGE_OBJECT = ['component' => 'Foo/Bar', 'props' => ['foo' => 'bar'], 'url' => '/test', 'version' => ''];

    public function setUp(): void
    {
        parent::setUp();

        $this->app->bind(Gateway::class, FakeGateway::class);
        $this->filesystem = m::mock(Filesystem::class);

        $this->compiler = new BladeCompiler($this->filesystem, __DIR__.'/cache/views');
        $this->compiler->directive('inertia', [Directive::class, 'compile']);
        $this->compiler->directive('inertiaHead', [Directive::class, 'compileHead']);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    protected function renderView($contents, $data = [])
    {
        // Laravel 8+ only: https://github.com/laravel/framework/pull/40425
        if (method_exists(BladeCompiler::class, 'render')) {
            return Blade::render($contents, $data, true);
        }

        // First, we'll create a temporary file, and use compileString to 'emulate' compilation of our view.
        // This skips caching, and a bunch of other logic that's not relevant for what we need here.
        $path = tempnam(sys_get_temp_dir(), 'inertia_tests_render_');
        file_put_contents($path, $this->compiler->compileString($contents));

        // Next, we'll 'render' out compiled view.
        $view = new View(
            m::mock(Factory::class),
            new PhpEngine(new Filesystem()),
            'fake-view',
            $path,
            $data
        );

        // Then, we'll just hack and slash our way to success..
        $view->getFactory()->allows('incrementRender')->once();
        $view->getFactory()->allows('callComposer')->once();
        $view->getFactory()->allows('getShared')->once()->andReturn([]);
        $view->getFactory()->allows('decrementRender')->once();
        $view->getFactory()->allows('flushStateIfDoneRendering')->once();
        $view->getFactory()->allows('flushState');

        try {
            $output = $view->render();
            @unlink($path);
        } catch (Throwable $e) {
            @unlink($path);
            throw $e;
        }

        return $output;
    }

    public function test_inertia_directive_renders_the_root_element(): void
    {
        Config::set(['inertia.ssr.enabled' => false]);

        $html = '<div id="app" data-page="{&quot;component&quot;:&quot;Foo\/Bar&quot;,&quot;props&quot;:{&quot;foo&quot;:&quot;bar&quot;},&quot;url&quot;:&quot;\/test&quot;,&quot;version&quot;:&quot;&quot;}"></div>';

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

        $html = '<div id="foo" data-page="{&quot;component&quot;:&quot;Foo\/Bar&quot;,&quot;props&quot;:{&quot;foo&quot;:&quot;bar&quot;},&quot;url&quot;:&quot;\/test&quot;,&quot;version&quot;:&quot;&quot;}"></div>';

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
        $this->app->instance(Gateway::class, $gateway = new FakeGateway());

        $view = "<!DOCTYPE html>\n<html>\n<head>\n@inertiaHead\n</head>\n<body>\n@inertia\n</body>\n</html>";
        $expected = "<!DOCTYPE html>\n<html>\n<head>\n<meta charset=\"UTF-8\" />\n<title inertia>Example SSR Title</title>\n</head>\n<body>\n<p>This is some example SSR content</p></body>\n</html>";

        $this->assertSame(
            $expected,
            $this->renderView($view, ['page' => self::EXAMPLE_PAGE_OBJECT])
        );

        $this->assertSame(1, $gateway->times);
    }
}
