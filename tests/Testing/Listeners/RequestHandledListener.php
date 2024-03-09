<?php

namespace Inertia\Tests\Testing\Listeners;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Inertia\Tests\TestCase;

class RequestHandledListener extends TestCase
{
    /** @test */
    public function the_request_is_stored()
    {
        $request = new Request();
        $response = new Response();

        $this->assertNull(app('inertia.testing.request'));

        Event::dispatch(new RequestHandled($request, $response));

        $this->assertSame($request, app('inertia.testing.request'));
    }
}
