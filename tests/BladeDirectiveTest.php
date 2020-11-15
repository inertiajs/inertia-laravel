<?php

namespace Inertia\Tests;

use Mockery as m;
use Illuminate\Support\Facades\Blade;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;

class BladeDirectiveTest extends TestCase
{
    protected function compileBlade(string $expression, $page = ['foo' => 'bar'])
    {
        $compiler = new BladeCompiler(m::mock(Filesystem::class), __DIR__);
        $compiler->directive('inertia', Blade::getCustomDirectives()['inertia']);

        $compiled = tap($compiler->compileString($expression), function () {
            m::close();
        });

        ob_start(function () {
            // This closure exists to prevent 'eval' output from getting sent to
            // STDOUT, which in turn causes PHPUnit to complain about it.
            return '';
        });

        eval("?>$compiled<?php ");

        return ob_get_flush();
    }

    public function test_directive_is_rendered_with_the_default_options()
    {
        $this->assertSame(
            '<div id="app" data-page="{&quot;foo&quot;:&quot;bar&quot;}"></div>',
            $this->compileBlade('@inertia')
        );
    }

    public function test_directive_is_rendered_with_a_custom_div_id()
    {
        $this->assertSame(
            '<div id="foo" data-page="{&quot;foo&quot;:&quot;bar&quot;}"></div>',
            $this->compileBlade("@inertia('foo')")
        );
    }
}
