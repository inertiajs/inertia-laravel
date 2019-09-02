<?php

namespace Inertia\Tests;

use Inertia\Response;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as BaseResponse;

class ResponseTest extends TestCase
{
    public function test_server_response()
    {
        $request = Request::create('/user/123', 'GET');

        $response = new Response(
            'User/Edit',
            ['user' => ['name' => 'Jonathan']],
            'app',
            '123'
        );

        $response = $response->toResponse($request);
        $view = $response->getOriginalContent();
        $page = $view->getData()['page'];

        $this->assertInstanceOf(BaseResponse::class, $response);
        $this->assertInstanceOf(View::class, $view);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame('<div id="app" data-page="{&quot;component&quot;:&quot;User\/Edit&quot;,&quot;props&quot;:{&quot;user&quot;:{&quot;name&quot;:&quot;Jonathan&quot;}},&quot;url&quot;:&quot;\/user\/123&quot;,&quot;version&quot;:&quot;123&quot;}"></div>'."\n", $view->render());
    }

    public function test_xhr_response()
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);

        $response = new Response(
            'User/Edit',
            ['user' => ['name' => 'Jonathan']],
            'app',
            '123'
        );

        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('User/Edit', $page->component);
        $this->assertSame('Jonathan', $page->props->user->name);
        $this->assertSame('/user/123', $page->url);
        $this->assertSame('123', $page->version);
    }
}
