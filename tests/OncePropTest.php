<?php

namespace Inertia\Tests;

use Illuminate\Http\Request;
use Inertia\OnceProp;

enum TestBackedEnum: string
{
    case Foo = 'foo-value';
}

enum TestUnitEnum
{
    case Baz;
}

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

    public function test_can_set_custom_key(): void
    {
        $onceProp = new OnceProp(fn () => 'value');

        $result = $onceProp->as('custom-key');
        $this->assertSame($onceProp, $result);
        $this->assertSame('custom-key', $onceProp->getKey());

        $onceProp->as(TestBackedEnum::Foo);
        $this->assertSame('foo-value', $onceProp->getKey());

        $onceProp->as(TestUnitEnum::Baz);
        $this->assertSame('Baz', $onceProp->getKey());
    }

    public function test_is_fresh_returns_false_by_default(): void
    {
        $onceProp = new OnceProp(fn () => 'value');

        $this->assertFalse($onceProp->markedAsFresh());
    }

    public function test_is_fresh_returns_true_after_fresh_called(): void
    {
        $onceProp = new OnceProp(fn () => 'value');
        $onceProp->fresh();

        $this->assertTrue($onceProp->markedAsFresh());
    }

    public function test_is_fresh_returns_false_after_fresh_false_called(): void
    {
        $onceProp = new OnceProp(fn () => 'value');
        $onceProp->fresh();
        $onceProp->fresh(false);

        $this->assertFalse($onceProp->markedAsFresh());
    }
}
