<?php

namespace Inertia\Tests;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response as BaseResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;
use Illuminate\View\View;
use Inertia\LazyProp;
use Inertia\Response;

class ResponseTest extends TestCase
{
    public function test_can_macro()
    {
        $response = new Response('User/Edit', []);
        $response->macro('foo', function () {
            return 'bar';
        });

        $this->assertEquals('bar', $response->foo());
    }

    public function test_server_response()
    {
        $request = Request::create('/user/123', 'GET');

        $user = ['name' => 'Jonathan'];
        $response = new Response('User/Edit', ['user' => $user], 'app', '123');
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

        $user = (object) ['name' => 'Jonathan'];
        $response = new Response('User/Edit', ['user' => $user], 'app', '123');
        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('User/Edit', $page->component);
        $this->assertSame('Jonathan', $page->props->user->name);
        $this->assertSame('/user/123', $page->url);
        $this->assertSame('123', $page->version);
    }

    public function test_resource_response()
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);

        $user = (object) ['name' => 'Jonathan'];

        $resource = new class($user) extends JsonResource {
            public static $wrap = null;

            public function toArray($request)
            {
                return ['name' => $this->name];
            }
        };

        $response = new Response('User/Edit', ['user' => $resource], 'app', '123');
        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('User/Edit', $page->component);
        $this->assertSame('Jonathan', $page->props->user->name);
        $this->assertSame('/user/123', $page->url);
        $this->assertSame('123', $page->version);
    }

    public function test_lazy_resource_response()
    {
        $request = Request::create('/users', 'GET', ['page' => 1]);
        $request->headers->add(['X-Inertia' => 'true']);

        $users = Collection::make([
            new Fluent(['name' => 'Jonathan']),
            new Fluent(['name' => 'Taylor']),
            new Fluent(['name' => 'Jeffrey']),
        ]);

        $callable = function () use ($users) {
            $page = new LengthAwarePaginator($users->take(2), $users->count(), 2);

            return new class($page, JsonResource::class) extends ResourceCollection {
            };
        };

        $response = new Response('User/Index', ['users' => $callable], 'app', '123');
        $response = $response->toResponse($request);
        $page = $response->getData();

        $expected = [
            'data' => $users->take(2),
            'links' => [
                'first' => '/?page=1',
                'last' => '/?page=2',
                'prev' => null,
                'next' => '/?page=2',
            ],
            'meta' => [
                'current_page' => 1,
                'from' => 1,
                'last_page' => 2,
                'path' => '/',
                'per_page' => 2,
                'to' => 2,
                'total' => 3,
            ],
        ];

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('User/Index', $page->component);
        $this->assertSame(json_encode($expected), json_encode($page->props->users));
        $this->assertSame('/users?page=1', $page->url);
        $this->assertSame('123', $page->version);
    }

    public function test_arrayable_prop_response()
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);

        $user = (object) ['name' => 'Jonathan'];

        $resource = new class($user) implements Arrayable {
            public $user;

            public function __construct($user)
            {
                $this->user = $user;
            }

            public function toArray()
            {
                return ['name' => $this->user->name];
            }
        };

        $response = new Response('User/Edit', ['user' => $resource], 'app', '123');
        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('User/Edit', $page->component);
        $this->assertSame('Jonathan', $page->props->user->name);
        $this->assertSame('/user/123', $page->url);
        $this->assertSame('123', $page->version);
    }

    public function test_xhr_partial_response()
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Partial-Component' => 'User/Edit']);
        $request->headers->add(['X-Inertia-Partial-Data' => 'partial']);

        $user = (object) ['name' => 'Jonathan'];
        $response = new Response('User/Edit', ['user' => $user, 'partial' => 'partial-data'], 'app', '123');
        $response = $response->toResponse($request);
        $page = $response->getData();

        $props = get_object_vars($page->props);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('User/Edit', $page->component);
        $this->assertFalse(isset($props['user']));
        $this->assertCount(1, $props);
        $this->assertSame('partial-data', $page->props->partial);
        $this->assertSame('/user/123', $page->url);
        $this->assertSame('123', $page->version);
    }

    public function test_lazy_props_are_not_included_by_default()
    {
        $request = Request::create('/users', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);

        $lazyProp = new LazyProp(function () {
            return 'A lazy value';
        });

        $response = new Response('Users', ['users' => [], 'lazy' => $lazyProp], 'app', '123');
        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertSame([], $page->props->users);
        $this->assertObjectNotHasAttribute('lazy', $page->props);
    }

    public function test_lazy_props_are_included_in_partial_reload()
    {
        $request = Request::create('/users', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Partial-Component' => 'Users']);
        $request->headers->add(['X-Inertia-Partial-Data' => 'lazy']);

        $lazyProp = new LazyProp(function () {
            return 'A lazy value';
        });

        $response = new Response('Users', ['users' => [], 'lazy' => $lazyProp], 'app', '123');
        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertObjectNotHasAttribute('users', $page->props);
        $this->assertSame('A lazy value', $page->props->lazy);
    }

    public function test_can_nest_props_using_dot_notation()
    {
        $request = Request::create('/products/123', 'GET');

        $props = [
            'auth' => [
                'user' => [
                    'name' => 'Jonathan Reinink',
                ],
            ],
            'auth.user.can.deleteProducts' => true,
            'product' => ['name' => 'My example product'],
        ];
        $response = new Response('Products/Edit', $props, 'app', '123');
        $response = $response->toResponse($request);
        $view = $response->getOriginalContent();
        $user = $view->getData()['page']['props']['auth']['user'];

        $this->assertSame('Jonathan Reinink', $user['name']);
        $this->assertTrue($user['can']['deleteProducts']);
    }
}
