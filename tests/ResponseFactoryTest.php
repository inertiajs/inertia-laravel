<?php

namespace Inertia\Tests;

use Inertia\ResponseFactory;

/**
 * @covers \Inertia\ResponseFactory
 * @uses \Inertia\Inertia
 * @uses \Inertia\ServiceProvider
 */
class ResponseFactoryTest extends TestCase
{
    public function test_can_macro()
    {
        $factory = new ResponseFactory();
        $factory->macro('foo', function () {
            return 'bar';
        });

        $this->assertEquals('bar', $factory->foo());
    }
}
