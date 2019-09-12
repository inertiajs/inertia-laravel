<?php

namespace Inertia\Tests;

use Inertia\Response;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;

class ResponseTest extends TestCase
{
    public function test_server_response()
    {
        $request = Request::create('/user/123', 'GET');

        $inertiaResponse = new Response(
            'User/Edit',
            ['user' => ['name' => 'Jonathan']],
            'app',
            '123'
        );

        $inertiaResponse->setStatusCode(418);

        $httpResponse = $inertiaResponse->toResponse($request);

        $this->assertInstanceOf(HttpResponse::class, $httpResponse);
        $this->assertSame(418, $httpResponse->getStatusCode());
        $this->assertSame('<div id="app" data-page="{&quot;component&quot;:&quot;User\/Edit&quot;,&quot;props&quot;:{&quot;user&quot;:{&quot;name&quot;:&quot;Jonathan&quot;}},&quot;url&quot;:&quot;\/user\/123&quot;,&quot;version&quot;:&quot;123&quot;}"></div>'.PHP_EOL, $httpResponse->getContent());
    }

    public function test_xhr_response()
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);

        $inertiaResponse = new Response(
            'User/Edit',
            ['user' => ['name' => 'Jonathan']],
            'app',
            '123'
        );

        $inertiaResponse->setStatusCode(418);

        $jsonResponse = $inertiaResponse->toResponse($request);

        $page = $jsonResponse->getData();

        $this->assertInstanceOf(JsonResponse::class, $jsonResponse);
        $this->assertSame(418, $jsonResponse->getStatusCode());
        $this->assertSame('User/Edit', $page->component);
        $this->assertSame('Jonathan', $page->props->user->name);
        $this->assertSame('/user/123', $page->url);
        $this->assertSame('123', $page->version);
    }
}
