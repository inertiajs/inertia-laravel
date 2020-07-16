<?php

namespace Inertia;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Assert as PHPUnit;

class Assertions
{
    public function assertInertia()
    {
        return function () {
            $this->assertViewHas('page');

            tap($this->viewData('page'), function ($view) {
                PHPUnit::assertArrayHasKey('component', $view);
                PHPUnit::assertArrayHasKey('props', $view);
                PHPUnit::assertArrayHasKey('url', $view);
                PHPUnit::assertArrayHasKey('version', $view);
            });

            return $this;
        };
    }

    public function inertiaProps()
    {
        return function () {
            $this->assertInertia();

            return $this->viewData('page')['props'];
        };
    }

    public function assertInertiaComponent()
    {
        return function ($name) {
            $this->assertInertia();

            PHPUnit::assertEquals($name, $this->viewData('page')['component']);

            return $this;
        };
    }

    public function assertInertiaHas()
    {
        return function ($key, $value = null) {
            if (is_array($key)) {
                return $this->assertInertiaHasAll($key);
            }

            if (is_null($value)) {
                PHPUnit::assertArrayHasKey($key, $this->inertiaProps());
            } elseif ($value instanceof Closure) {
                PHPUnit::assertTrue($value(Arr::get($this->inertiaProps(), $key)));
            } elseif ($value instanceof Model) {
                PHPUnit::assertEquals($value->toArray(), Arr::get($this->inertiaProps(), $key));
            } else {
                PHPUnit::assertEquals($value, Arr::get($this->inertiaProps(), $key));
            }

            return $this;
        };
    }

    // TODO: Test.
    public function assertInertiaHasAll()
    {
        return function (array $bindings) {
            foreach ($bindings as $key => $value) {
                if (is_int($key)) {
                    $this->assertInertiaHas($value);
                } else {
                    $this->assertInertiaHas($key, $value);
                }
            }

            return $this;
        };
    }

    // TODO: Test.
    public function assertInertiaMissing()
    {
        return function ($key) {
            $this->assertInertia();

            PHPUnit::assertArrayNotHasKey($key, $this->inertiaProps());

            return $this;
        };
    }
}
