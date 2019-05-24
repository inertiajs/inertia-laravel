<?php

namespace Tests;

use Inertia\Response;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\ResponseFactory;

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
        $page = $response->getData()['page'];

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame('<div id="app" data-page="{&quot;component&quot;:&quot;User\/Edit&quot;,&quot;props&quot;:{&quot;user&quot;:{&quot;name&quot;:&quot;Jonathan&quot;}},&quot;url&quot;:&quot;\/user\/123&quot;,&quot;version&quot;:&quot;123&quot;}"></div>'."\n", $response->render());
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

    public function test_the_helper_function_returns_a_response_instance()
    {
        $this->assertInstanceOf(Response::class, inertia('User/Edit', ['user' => ['name' => 'Jonathan']]));
    }

    public function test_if_no_parameters_are_passed_to_the_helper_function_it_returns_the_response_factory()
    {
        $this->assertInstanceOf(ResponseFactory::class, inertia());
    }
}
