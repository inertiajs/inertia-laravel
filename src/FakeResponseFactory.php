<?php


namespace Inertia;


use Illuminate\Contracts\Support\Arrayable;
use PHPUnit\Framework\Assert;

class FakeResponseFactory extends ResponseFactory
{

    private $component;
    private $props;

    public function render($component, $props = [])
    {
        if($props instanceof Arrayable) {
            $props = $props->toArray();
        }

        $this->component = $component;
        $this->props     = $props;

        return new Response(
            $component,
            array_merge($this->sharedProps, $props),
            $this->rootView,
            $this->getVersion()
        );
    }

    public function assertHasProps($props = [])
    {
        $allProps = array_keys($this->getAllProps());

        foreach($props as $prop) {
            Assert::assertTrue(
                in_array($prop, $allProps),
                "Failed asserting that prop $prop exists"
            );
        }
    }

    public function assertHasProp($prop = '')
    {
        $this->assertHasProps([$prop]);
    }

    public function assertPropsEqual($expected)
    {
        $allProps = $this->getAllProps();

        Assert::assertEquals($expected, $allProps);
    }

    public function assertPropEquals($key, $value)
    {
        $this->assertHasProp($key);

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
        return array_merge($this->sharedProps, $this->props);
    }

    public function getProp($key)
    {
        return $this->getAllProps()[$key];
    }

}