<?php

namespace Inertia\Tests;

use Illuminate\Http\Request;
use Inertia\LazyProp;

class LazyPropTest extends TestCase
{
    public function test_can_invoke()
    {
        $lazyProp = new LazyProp(function () {
            return 'A lazy value';
        });

        $this->assertSame('A lazy value', $lazyProp());
    }

    public function test_can_resolve_bindings_when_invoked()
    {
        $lazyProp = new LazyProp(function (Request $request) {
            return $request;
        });

        $this->assertInstanceOf(Request::class, $lazyProp());
    }
}
