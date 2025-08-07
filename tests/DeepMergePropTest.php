<?php

namespace Inertia\Tests;

use Illuminate\Http\Request;
use Inertia\MergeProp;

class DeepMergePropTest extends TestCase
{
    public function test_can_invoke_with_a_callback(): void
    {
        $mergeProp = (new MergeProp(fn () => 'A merge prop value'))->deepMerge();

        $this->assertSame('A merge prop value', $mergeProp());
    }

    public function test_can_invoke_with_a_non_callback(): void
    {
        $mergeProp = (new MergeProp(['key' => 'value']))->deepMerge();

        $this->assertSame(['key' => 'value'], $mergeProp());
    }

    public function test_can_resolve_bindings_when_invoked(): void
    {
        $mergeProp = (new MergeProp(fn (Request $request) => $request))->deepMerge();

        $this->assertInstanceOf(Request::class, $mergeProp());
    }

    public function test_can_use_single_string_as_key_to_match_on(): void
    {
        $mergeProp = (new MergeProp(['key' => 'value']))->matchOn('key');

        $this->assertEquals(['key'], $mergeProp->matchesOn());
    }

    public function test_can_use_an_array_of_strings_as_keys_to_match_on(): void
    {
        $mergeProp = (new MergeProp(['key' => 'value']))->matchOn(['key', 'anotherKey']);

        $this->assertEquals(['key', 'anotherKey'], $mergeProp->matchesOn());
    }
}
