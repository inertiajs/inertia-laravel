<?php

namespace Inertia\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Inertia\Directive;
use Mockery as m;

class DirectiveTest extends TestCase
{
    /**
     * @var BladeCompiler
     */
    protected $compiler;

    public function setUp(): void
    {
        $this->compiler = new BladeCompiler($this->getFiles(), __DIR__);
        $this->compiler->directive('inertia', [Directive::class, 'compile']);
        parent::setUp();
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    protected function getFiles()
    {
        return m::mock(Filesystem::class);
    }

    public function test_it_renders_the_root_element_by_default(): void
    {
        $this->assertSame('<div id="app" data-page="<?php echo e(json_encode($page)); ?>"></div>', $this->compiler->compileString('@inertia'));
    }

    public function test_blade_directive_can_indicate_ssr_head_placement(): void
    {
        // TODO
    }
}
