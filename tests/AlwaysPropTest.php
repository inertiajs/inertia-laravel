<?php

namespace Inertia\Tests;

use Inertia\AlwaysProp;
use Illuminate\Http\Request;

class AlwaysPropTest extends TestCase
{
    public function test_can_invoke(): void
    {
        $alwaysProp = new AlwaysProp(function () {
            return 'An always value';
        });

        $this->assertSame('An always value', $alwaysProp());
    }

    public function test_can_accept_scalar_values(): void
    {
        $alwaysProp = new AlwaysProp('An always value');

        $this->assertSame('An always value', $alwaysProp());
    }

    public function test_can_accept_callables(): void
    {
        $callable = new class() {
            public function __invoke()
            {
                return 'An always value';
            }
        };

        $alwaysProp = new AlwaysProp($callable);

        $this->assertSame('An always value', $alwaysProp());
    }

    public function test_can_resolve_bindings_when_invoked(): void
    {
        $alwaysProp = new AlwaysProp(function (Request $request) {
            return $request;
        });

        $this->assertInstanceOf(Request::class, $alwaysProp());
    }
}
