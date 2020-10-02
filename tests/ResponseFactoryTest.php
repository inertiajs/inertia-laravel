<?php

namespace Inertia\Tests;

use Inertia\ResponseFactory;
use Illuminate\Http\Response;

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

    public function test_location_response()
    {
        $response = (new ResponseFactory())->location('https://inertiajs.com');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(409, $response->getStatusCode());
        $this->assertEquals('https://inertiajs.com', $response->headers->get('X-Inertia-Location'));
    }
}
