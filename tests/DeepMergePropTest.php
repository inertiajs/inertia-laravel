<?php

namespace Inertia\Tests;

use Illuminate\Http\Request;
use Inertia\MergeProp;

class DeepMergePropTest extends TestCase
{
    public function test_can_invoke_with_a_callback(): void
    {
        $mergeProp = new MergeProp(function () {
            return 'A merge prop value';
        });
        $mergeProp->deepMerge();

        $this->assertSame('A merge prop value', $mergeProp());
    }

    public function test_can_invoke_with_a_non_callback(): void
    {
        $mergeProp = new MergeProp(['key' => 'value']);
        $mergeProp->deepMerge();

        $this->assertSame(['key' => 'value'], $mergeProp());
    }

    public function test_can_resolve_bindings_when_invoked(): void
    {
        $mergeProp = new MergeProp(function (Request $request) {
            return $request;
        });
        $mergeProp->deepMerge();

        $this->assertInstanceOf(Request::class, $mergeProp());
    }
}
