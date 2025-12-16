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

    public function test_string_function_names_are_not_invoked(): void
    {
        $onceProp = new OnceProp('date');

        $this->assertSame('date', $onceProp());
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

    public function test_should_not_be_refreshed_by_default(): void
    {
        $onceProp = new OnceProp(fn () => 'value');

        $this->assertFalse($onceProp->shouldBeRefreshed());
    }

    public function test_can_forcefully_refresh(): void
    {
        $onceProp = new OnceProp(fn () => 'value');
        $onceProp->fresh();

        $this->assertTrue($onceProp->shouldBeRefreshed());
    }

    public function test_can_disable_forceful_refresh(): void
    {
        $onceProp = new OnceProp(fn () => 'value');
        $onceProp->fresh();
        $onceProp->fresh(false);

        $this->assertFalse($onceProp->shouldBeRefreshed());
    }
}
