<?php

namespace Inertia\Tests;

use Illuminate\Http\Request;
use Inertia\DeferProp;

class DeferPropTest extends TestCase
{
    public function test_can_invoke(): void
    {
        $deferProp = new DeferProp(function () {
            return 'A deferred value';
        }, 'default');

        $this->assertSame('A deferred value', $deferProp());
        $this->assertSame('default', $deferProp->group());
    }

    public function test_string_function_names_are_not_invoked(): void
    {
        $deferProp = new DeferProp('date');

        $this->assertSame('date', $deferProp());
    }

    public function test_can_invoke_and_merge(): void
    {
        $deferProp = (new DeferProp(function () {
            return 'A deferred value';
        }))->merge();

        $this->assertSame('A deferred value', $deferProp());
    }

    public function test_can_resolve_bindings_when_invoked(): void
    {
        $deferProp = new DeferProp(function (Request $request) {
            return $request;
        });

        $this->assertInstanceOf(Request::class, $deferProp());
    }

    public function test_is_onceable(): void
    {
        $deferProp = (new DeferProp(fn () => 'value'))
            ->once(as: 'custom-key', until: 60);

        $this->assertTrue($deferProp->shouldResolveOnce());
        $this->assertSame('custom-key', $deferProp->getKey());
        $this->assertNotNull($deferProp->expiresAt());
    }
}
