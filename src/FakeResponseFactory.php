<?php


namespace Inertia;


use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use PHPUnit\Framework\Assert;

class FakeResponseFactory extends ResponseFactory
{

    private $component = '';
    private $props     = [];

    public function render($component, $props = [])
    {
        if($props instanceof Arrayable) {
            $props = $props->toArray();
        }

        $props = $this->transformProps(array_merge($this->sharedProps, $props));

        $this->props     = $props;
        $this->component = $component;


        return new Response(
            $component,
            array_merge($this->sharedProps, $props),
            $this->rootView,
            $this->getVersion()
        );
    }

    private function transformProps($props)
    {
        array_walk_recursive($props, [$this, 'transformProp']);

        return $props;
    }

    private function transformProp(&$prop)
    {
        if($prop instanceof Closure) {
            $prop = App::call($prop);
        }

        if($prop instanceof Responsable) {
            $prop = $prop->toResponse(new Request)->getData();
        }

        if($prop instanceof Arrayable) {
            $prop = $prop->toArray();
        }
    }

    public function assertHasProps($propKeys = [])
    {
        $allPropKeys = array_keys($this->getAllProps());

        foreach($propKeys as $key) {
            Assert::assertTrue(
                in_array($key, $allPropKeys),
                "Failed asserting that prop $key exists"
            );
        }
    }

    public function assertHasProp($prop = '')
    {
        $this->assertHasProps([$prop]);
    }

    public function assertPropsEqual($expected)
    {
        $expected = $this->transformProps($expected);
        $allProps = $this->getAllProps();

        Assert::assertEquals($expected, $allProps);
    }

    public function assertPropEquals($key, $value)
    {
        $this->assertHasProp($key);

        $this->transformProp($value);

        $prop = $this->getProp($key);

        Assert::assertEquals($value, $prop);
    }

    public function assertComponentIs($component = '')
    {
        Assert::assertEquals(
            $component,
            $this->component,
            "Failed asserting that $component was rendered. When $this->component was rendered."
        );
    }

    public function getAllProps()
    {
        return $this->props;
    }

    public function getProp($key)
    {
        return $this->getAllProps()[$key];
    }

}