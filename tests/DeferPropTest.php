<?php

namespace Inertia\Tests;

use Illuminate\Http\Request;
use Inertia\DeferProp;

class DeferPropTest extends TestCase
{
    public function test_can_invoke(): void
    {
        $deferProp = new DeferProp(function () {
            return 'A lazy value';
        });

        $this->assertSame('A lazy value', $deferProp());
    }

    public function test_can_resolve_bindings_when_invoked(): void
    {
        $deferProp = new DeferProp(function (Request $request) {
            return $request;
        });

        $this->assertInstanceOf(Request::class, $deferProp());
    }
}
