<?php

namespace Inertia\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\PhpEngine;
use Illuminate\View\Factory;
use Illuminate\View\View;
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

    /**
     * Example Page Object.
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
        } catch (\Throwable $e) {
            @unlink($path);
            throw $e;
        }

        return $output;
    }

    public function test_it_renders_the_root_element_by_default(): void
    {
        $defaultElement = '<div id="app" data-page="{&quot;component&quot;:&quot;Foo\/Bar&quot;,&quot;props&quot;:{&quot;foo&quot;:&quot;bar&quot;},&quot;url&quot;:&quot;\/test&quot;,&quot;version&quot;:&quot;&quot;}"></div>';
        $fooElement = '<div id="foo" data-page="{&quot;component&quot;:&quot;Foo\/Bar&quot;,&quot;props&quot;:{&quot;foo&quot;:&quot;bar&quot;},&quot;url&quot;:&quot;\/test&quot;,&quot;version&quot;:&quot;&quot;}"></div>';

        $this->assertSame($defaultElement, $this->renderView('@inertia', ['page' => self::EXAMPLE_PAGE_OBJECT]));
        $this->assertSame($defaultElement, $this->renderView('@inertia()', ['page' => self::EXAMPLE_PAGE_OBJECT]));
        $this->assertSame($defaultElement, $this->renderView('@inertia("")', ['page' => self::EXAMPLE_PAGE_OBJECT]));
        $this->assertSame($defaultElement, $this->renderView("@inertia('')", ['page' => self::EXAMPLE_PAGE_OBJECT]));
        $this->assertSame($fooElement, $this->renderView('@inertia(foo)', ['page' => self::EXAMPLE_PAGE_OBJECT]));
        $this->assertSame($fooElement, $this->renderView("@inertia('foo')", ['page' => self::EXAMPLE_PAGE_OBJECT]));
        $this->assertSame($fooElement, $this->renderView('@inertia("foo")', ['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function test_blade_directive_can_indicate_ssr_head_placement(): void
    {
        $this->assertSame("foo\nbar\n", $this->renderView('@inertiaHead', ['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function test_renders_ssr_content_instead_of_the_default_root_element(): void
    {
        $view = "<!DOCTYPE html>\n<html>\n<head>\n@inertiaHead\n</head>\n<body>\n@inertia\n</body>\n</html>";
        $expected = "<!DOCTYPE html>\n<html>\n<head>\nfoo\nbar\n</head>\n<body>\nbaz</body>\n</html>";

        $this->assertSame($expected, $this->renderView($view, ['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    /** @test */
    public function falls_back_to_client_side_rendering_when_server_side_rendering_fails(): void
    {
        $view = "<!DOCTYPE html>\n<html>\n<head>\n@inertiaHead\n</head>\n<body>\n@inertia\n</body>\n</html>";
        $expected = "<!DOCTYPE html>\n<html>\n<head>\n</head>\n<body>\n<div id=\"app\" data-page=\"{&quot;component&quot;:&quot;Ssr\\/Fail&quot;,&quot;props&quot;:{&quot;foo&quot;:&quot;bar&quot;},&quot;url&quot;:&quot;\\/test&quot;,&quot;version&quot;:&quot;&quot;}\"></div></body>\n</html>";

        $page = array_merge(self::EXAMPLE_PAGE_OBJECT, [
            'component' => 'Ssr/Fail', // Special flag to emulate SSR failure. See \Inertia\Tests\Stubs\FakeGateway.
        ]);

        $this->assertSame($expected, $this->renderView($view, ['page' => $page]));
    }
}
