<?php

namespace Inertia\Tests;

use Illuminate\Http\Request;
use Inertia\OnceProp;

class OncePropTest extends TestCase
{
    public function test_can_invoke_with_a_callback(): void
    {
        $onceProp = new OnceProp(function () {
            return 'A once prop value';
        });

        $this->assertSame('A once prop value', $onceProp());
    }

    public function test_can_resolve_bindings_when_invoked(): void
    {
        $onceProp = new OnceProp(function (Request $request) {
            return $request;
        });

        $this->assertInstanceOf(Request::class, $onceProp());
    }
}
