<?php

namespace Inertia\Tests;

use Illuminate\Http\Request;
use Inertia\OptionalProp;

class OptionalPropTest extends TestCase
{
    public function test_can_invoke(): void
    {
        $optionalProp = new OptionalProp(function () {
            return 'A lazy value';
        });

        $this->assertSame('A lazy value', $optionalProp());
    }

    public function test_string_function_names_are_not_invoked(): void
    {
        $optionalProp = new OptionalProp('date');

        $this->assertSame('date', $optionalProp());
    }

    public function test_can_resolve_bindings_when_invoked(): void
    {
        $optionalProp = new OptionalProp(function (Request $request) {
            return $request;
        });

        $this->assertInstanceOf(Request::class, $optionalProp());
    }

    public function test_is_onceable(): void
    {
        $optionalProp = (new OptionalProp(fn () => 'value'))
            ->once()
            ->as('custom-key')
            ->until(60);

        $this->assertTrue($optionalProp->shouldResolveOnce());
        $this->assertSame('custom-key', $optionalProp->getKey());
        $this->assertNotNull($optionalProp->expiresAt());
    }
}
