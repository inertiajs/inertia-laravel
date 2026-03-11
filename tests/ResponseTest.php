<?php

namespace Inertia\Tests;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response as BaseResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;
use Illuminate\View\View;
use Inertia\AlwaysProp;
use Inertia\DeferProp;
use Inertia\Inertia;
use Inertia\LazyProp;
use Inertia\MergeProp;
use Inertia\ProvidesInertiaProperties;
use Inertia\ProvidesScrollMetadata;
use Inertia\RenderContext;
use Inertia\Response;
use Inertia\ScrollProp;
use Inertia\Tests\Stubs\FakeResource;
use Inertia\Tests\Stubs\MergeWithSharedProp;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;

class ResponseTest extends TestCase
{
    public function test_can_macro(): void
    {
        $response = new Response('User/Edit', []);
        $response->macro('foo', function () {
            return 'bar';
        });

        /** @phpstan-ignore-next-line */
        $this->assertEquals('bar', $response->foo());
    }

    public function test_server_response(): void
    {
        $request = Request::create('/user/123', 'GET');

        $user = ['name' => 'Jonathan'];
        $response = new Response('User/Edit', ['user' => $user], 'app', '123');
        /** @var BaseResponse $response */
        $response = $response->toResponse($request);
        $view = $response->getOriginalContent();
        $page = $view->getData()['page'];

        $this->assertInstanceOf(BaseResponse::class, $response);
        $this->assertInstanceOf(View::class, $view);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertFalse($page['clearHistory']);
        $this->assertFalse($page['encryptHistory']);
        $this->assertSame('<div id="app" data-page="{&quot;component&quot;:&quot;User\/Edit&quot;,&quot;props&quot;:{&quot;user&quot;:{&quot;name&quot;:&quot;Jonathan&quot;}},&quot;url&quot;:&quot;\/user\/123&quot;,&quot;version&quot;:&quot;123&quot;,&quot;clearHistory&quot;:false,&quot;encryptHistory&quot;:false}"></div>', $view->render());
    }

    public function test_server_response_with_deferred_prop(): void
    {
        $request = Request::create('/user/123', 'GET');

        $user = ['name' => 'Jonathan'];
        $response = new Response(
            'User/Edit',
            [
                'user' => $user,
                'foo' => new DeferProp(function () {
                    return 'bar';
                }, 'default'),
            ],
            'app',
            '123'
        );
        $response = $response->toResponse($request);
        /** @var BaseResponse $response */
        $view = $response->getOriginalContent();
        $page = $view->getData()['page'];

        $this->assertInstanceOf(BaseResponse::class, $response);
        $this->assertInstanceOf(View::class, $view);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame([
            'default' => ['foo'],
        ], $page['deferredProps']);
        $this->assertFalse($page['clearHistory']);
        $this->assertFalse($page['encryptHistory']);
        $this->assertSame('<div id="app" data-page="{&quot;component&quot;:&quot;User\/Edit&quot;,&quot;props&quot;:{&quot;user&quot;:{&quot;name&quot;:&quot;Jonathan&quot;}},&quot;url&quot;:&quot;\/user\/123&quot;,&quot;version&quot;:&quot;123&quot;,&quot;clearHistory&quot;:false,&quot;encryptHistory&quot;:false,&quot;deferredProps&quot;:{&quot;default&quot;:[&quot;foo&quot;]}}"></div>', $view->render());
    }

    public function test_server_response_with_deferred_prop_and_multiple_groups(): void
    {
        $request = Request::create('/user/123', 'GET');

        $user = ['name' => 'Jonathan'];
        $response = new Response(
            'User/Edit',
            [
                'user' => $user,
                'foo' => new DeferProp(function () {
                    return 'foo value';
                }, 'default'),
                'bar' => new DeferProp(function () {
                    return 'bar value';
                }, 'default'),
                'baz' => new DeferProp(function () {
                    return 'baz value';
                }, 'custom'),
            ],
            'app',
            '123'
        );
        $response = $response->toResponse($request);
        /** @var BaseResponse $response */
        $view = $response->getOriginalContent();
        $page = $view->getData()['page'];

        $this->assertInstanceOf(BaseResponse::class, $response);
        $this->assertInstanceOf(View::class, $view);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame([
            'default' => ['foo', 'bar'],
            'custom' => ['baz'],
        ], $page['deferredProps']);
        $this->assertFalse($page['clearHistory']);
        $this->assertFalse($page['encryptHistory']);
        $this->assertSame('<div id="app" data-page="{&quot;component&quot;:&quot;User\/Edit&quot;,&quot;props&quot;:{&quot;user&quot;:{&quot;name&quot;:&quot;Jonathan&quot;}},&quot;url&quot;:&quot;\/user\/123&quot;,&quot;version&quot;:&quot;123&quot;,&quot;clearHistory&quot;:false,&quot;encryptHistory&quot;:false,&quot;deferredProps&quot;:{&quot;default&quot;:[&quot;foo&quot;,&quot;bar&quot;],&quot;custom&quot;:[&quot;baz&quot;]}}"></div>', $view->render());
    }

    /**
     * @return array<string, array{0: bool}>
     */
    public static function resetUsersProp(): array
    {
        return [
            'no reset' => [false],
            'with reset' => [true],
        ];
    }

    #[DataProvider('resetUsersProp')]
    public function test_server_response_with_scroll_props(bool $resetUsersProp): void
    {
        $request = Request::create('/user/123', 'GET');

        if ($resetUsersProp) {
            $request->headers->add(['X-Inertia-Reset' => 'users']);
        }

        $response = new Response(
            'User/Index',
            [
                'users' => new ScrollProp(['data' => [['id' => 1]]], 'data', new class implements ProvidesScrollMetadata
                {
                    public function getPageName(): string
                    {
                        return 'page';
                    }

                    public function getPreviousPage(): ?int
                    {
                        return null;
                    }

                    public function getNextPage(): int
                    {
                        return 2;
                    }

                    public function getCurrentPage(): int
                    {
                        return 1;
                    }
                }),
            ],
            'app',
            '123'
        );
        $response = $response->toResponse($request);
        /** @var BaseResponse $response */
        $view = $response->getOriginalContent();
        $page = $view->getData()['page'];

        $this->assertInstanceOf(BaseResponse::class, $response);
        $this->assertInstanceOf(View::class, $view);

        $this->assertSame('User/Index', $page['component']);
        $this->assertSame(['data' => [['id' => 1]]], $page['props']['users']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame([
            'users' => [
                'pageName' => 'page',
                'previousPage' => null,
                'nextPage' => 2,
                'currentPage' => 1,
                'reset' => $resetUsersProp,
            ],
        ], $page['scrollProps']);
    }

    public function test_server_response_with_merge_props(): void
    {
        $request = Request::create('/user/123', 'GET');

        $user = ['name' => 'Jonathan'];
        $response = new Response(
            'User/Edit',
            [
                'user' => $user,
                'foo' => new MergeProp('foo value'),
                'bar' => new MergeProp('bar value'),
            ],
            'app',
            '123'
        );
        $response = $response->toResponse($request);
        /** @var BaseResponse $response */
        $view = $response->getOriginalContent();
        $page = $view->getData()['page'];

        $this->assertInstanceOf(BaseResponse::class, $response);
        $this->assertInstanceOf(View::class, $view);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame([
            'foo',
            'bar',
        ], $page['mergeProps']);
        $this->assertFalse($page['clearHistory']);
        $this->assertFalse($page['encryptHistory']);
        $this->assertSame('<div id="app" data-page="{&quot;component&quot;:&quot;User\/Edit&quot;,&quot;props&quot;:{&quot;user&quot;:{&quot;name&quot;:&quot;Jonathan&quot;},&quot;foo&quot;:&quot;foo value&quot;,&quot;bar&quot;:&quot;bar value&quot;},&quot;url&quot;:&quot;\/user\/123&quot;,&quot;version&quot;:&quot;123&quot;,&quot;clearHistory&quot;:false,&quot;encryptHistory&quot;:false,&quot;mergeProps&quot;:[&quot;foo&quot;,&quot;bar&quot;]}"></div>', $view->render());
    }

    public function test_server_response_with_merge_props_that_should_prepend(): void
    {
        $request = Request::create('/user/123', 'GET');

        $user = ['name' => 'Jonathan'];
        $response = new Response(
            'User/Edit',
            [
                'user' => $user,
                'foo' => (new MergeProp('foo value'))->prepend(),
                'bar' => new MergeProp('bar value'),
            ],
            'app',
            '123'
        );
        $response = $response->toResponse($request);
        /** @var BaseResponse $response */
        $view = $response->getOriginalContent();
        $page = $view->getData()['page'];

        $this->assertInstanceOf(BaseResponse::class, $response);
        $this->assertInstanceOf(View::class, $view);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame(['bar'], $page['mergeProps']);
        $this->assertSame(['foo'], $page['prependProps']);
        $this->assertFalse($page['clearHistory']);
        $this->assertFalse($page['encryptHistory']);
        $this->assertSame('<div id="app" data-page="{&quot;component&quot;:&quot;User\/Edit&quot;,&quot;props&quot;:{&quot;user&quot;:{&quot;name&quot;:&quot;Jonathan&quot;},&quot;foo&quot;:&quot;foo value&quot;,&quot;bar&quot;:&quot;bar value&quot;},&quot;url&quot;:&quot;\/user\/123&quot;,&quot;version&quot;:&quot;123&quot;,&quot;clearHistory&quot;:false,&quot;encryptHistory&quot;:false,&quot;mergeProps&quot;:[&quot;bar&quot;],&quot;prependProps&quot;:[&quot;foo&quot;]}"></div>', $view->render());
    }

    public function test_server_response_with_merge_props_that_has_nested_paths_to_append_and_prepend(): void
    {
        $request = Request::create('/user/123', 'GET');

        $user = ['name' => 'Jonathan'];
        $response = new Response(
            'User/Edit',
            [
                'user' => $user,
                'foo' => (new MergeProp(['data' => [['id' => 1], ['id' => 2]]]))->append('data'),
                'bar' => (new MergeProp(['data' => ['items' => [['uuid' => 1], ['uuid' => 2]]]]))->prepend('data.items'),
            ],
            'app',
            '123'
        );
        $response = $response->toResponse($request);
        /** @var BaseResponse $response */
        $view = $response->getOriginalContent();
        $page = $view->getData()['page'];

        $this->assertInstanceOf(BaseResponse::class, $response);
        $this->assertInstanceOf(View::class, $view);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame(['foo.data'], $page['mergeProps']);
        $this->assertSame(['bar.data.items'], $page['prependProps']);
        $this->assertArrayNotHasKey('matchPropsOn', $page);
        $this->assertFalse($page['clearHistory']);
        $this->assertFalse($page['encryptHistory']);
        $this->assertSame('<div id="app" data-page="{&quot;component&quot;:&quot;User\/Edit&quot;,&quot;props&quot;:{&quot;user&quot;:{&quot;name&quot;:&quot;Jonathan&quot;},&quot;foo&quot;:{&quot;data&quot;:[{&quot;id&quot;:1},{&quot;id&quot;:2}]},&quot;bar&quot;:{&quot;data&quot;:{&quot;items&quot;:[{&quot;uuid&quot;:1},{&quot;uuid&quot;:2}]}}},&quot;url&quot;:&quot;\/user\/123&quot;,&quot;version&quot;:&quot;123&quot;,&quot;clearHistory&quot;:false,&quot;encryptHistory&quot;:false,&quot;mergeProps&quot;:[&quot;foo.data&quot;],&quot;prependProps&quot;:[&quot;bar.data.items&quot;]}"></div>', $view->render());
    }

    public function test_server_response_with_merge_props_that_has_nested_paths_to_append_and_prepend_with_match_on_strategies(): void
    {
        $request = Request::create('/user/123', 'GET');

        $user = ['name' => 'Jonathan'];
        $response = new Response(
            'User/Edit',
            [
                'user' => $user,
                'foo' => (new MergeProp(['data' => [['id' => 1], ['id' => 2]]]))->append('data', 'id'),
                'bar' => (new MergeProp(['data' => ['items' => [['uuid' => 1], ['uuid' => 2]]]]))->prepend('data.items', 'uuid'),
            ],
            'app',
            '123'
        );
        $response = $response->toResponse($request);
        /** @var BaseResponse $response */
        $view = $response->getOriginalContent();
        $page = $view->getData()['page'];

        $this->assertInstanceOf(BaseResponse::class, $response);
        $this->assertInstanceOf(View::class, $view);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame(['foo.data'], $page['mergeProps']);
        $this->assertSame(['bar.data.items'], $page['prependProps']);
        $this->assertSame(['foo.data.id', 'bar.data.items.uuid'], $page['matchPropsOn']);
        $this->assertFalse($page['clearHistory']);
        $this->assertFalse($page['encryptHistory']);
        $this->assertSame('<div id="app" data-page="{&quot;component&quot;:&quot;User\/Edit&quot;,&quot;props&quot;:{&quot;user&quot;:{&quot;name&quot;:&quot;Jonathan&quot;},&quot;foo&quot;:{&quot;data&quot;:[{&quot;id&quot;:1},{&quot;id&quot;:2}]},&quot;bar&quot;:{&quot;data&quot;:{&quot;items&quot;:[{&quot;uuid&quot;:1},{&quot;uuid&quot;:2}]}}},&quot;url&quot;:&quot;\/user\/123&quot;,&quot;version&quot;:&quot;123&quot;,&quot;clearHistory&quot;:false,&quot;encryptHistory&quot;:false,&quot;mergeProps&quot;:[&quot;foo.data&quot;],&quot;prependProps&quot;:[&quot;bar.data.items&quot;],&quot;matchPropsOn&quot;:[&quot;foo.data.id&quot;,&quot;bar.data.items.uuid&quot;]}"></div>', $view->render());
    }

    public function test_server_response_with_deep_merge_props(): void
    {
        $request = Request::create('/user/123', 'GET');

        $user = ['name' => 'Jonathan'];
        $response = new Response(
            'User/Edit',
            [
                'user' => $user,
                'foo' => (new MergeProp('foo value'))->deepMerge(),
                'bar' => (new MergeProp('bar value'))->deepMerge(),
            ],
            'app',
            '123'
        );
        $response = $response->toResponse($request);
        /** @var BaseResponse $response */
        $view = $response->getOriginalContent();
        $page = $view->getData()['page'];

        $this->assertInstanceOf(BaseResponse::class, $response);
        $this->assertInstanceOf(View::class, $view);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame([
            'foo',
            'bar',
        ], $page['deepMergeProps']);
        $this->assertFalse($page['clearHistory']);
        $this->assertFalse($page['encryptHistory']);
        $this->assertSame('<div id="app" data-page="{&quot;component&quot;:&quot;User\/Edit&quot;,&quot;props&quot;:{&quot;user&quot;:{&quot;name&quot;:&quot;Jonathan&quot;},&quot;foo&quot;:&quot;foo value&quot;,&quot;bar&quot;:&quot;bar value&quot;},&quot;url&quot;:&quot;\/user\/123&quot;,&quot;version&quot;:&quot;123&quot;,&quot;clearHistory&quot;:false,&quot;encryptHistory&quot;:false,&quot;deepMergeProps&quot;:[&quot;foo&quot;,&quot;bar&quot;]}"></div>', $view->render());
    }

    public function test_server_response_with_match_on_props(): void
    {
        $request = Request::create('/user/123', 'GET');

        $user = ['name' => 'Jonathan'];
        $response = new Response(
            'User/Edit',
            [
                'user' => $user,
                'foo' => (new MergeProp('foo value'))->matchOn('foo-key')->deepMerge(),
                'bar' => (new MergeProp('bar value'))->matchOn('bar-key')->deepMerge(),
            ],
            'app',
            '123'
        );
        $response = $response->toResponse($request);
        /** @var BaseResponse $response */
        $view = $response->getOriginalContent();
        $page = $view->getData()['page'];

        $this->assertInstanceOf(BaseResponse::class, $response);
        $this->assertInstanceOf(View::class, $view);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame([
            'foo',
            'bar',
        ], $page['deepMergeProps']);

        $this->assertSame([
            'foo.foo-key',
            'bar.bar-key',
        ], $page['matchPropsOn']);
        $this->assertFalse($page['clearHistory']);
        $this->assertFalse($page['encryptHistory']);
        $this->assertSame('<div id="app" data-page="{&quot;component&quot;:&quot;User\/Edit&quot;,&quot;props&quot;:{&quot;user&quot;:{&quot;name&quot;:&quot;Jonathan&quot;},&quot;foo&quot;:&quot;foo value&quot;,&quot;bar&quot;:&quot;bar value&quot;},&quot;url&quot;:&quot;\/user\/123&quot;,&quot;version&quot;:&quot;123&quot;,&quot;clearHistory&quot;:false,&quot;encryptHistory&quot;:false,&quot;deepMergeProps&quot;:[&quot;foo&quot;,&quot;bar&quot;],&quot;matchPropsOn&quot;:[&quot;foo.foo-key&quot;,&quot;bar.bar-key&quot;]}"></div>', $view->render());
    }

    public function test_server_response_with_defer_and_merge_props(): void
    {
        $request = Request::create('/user/123', 'GET');

        $user = ['name' => 'Jonathan'];
        $response = new Response(
            'User/Edit',
            [
                'user' => $user,
                'foo' => (new DeferProp(function () {
                    return 'foo value';
                }, 'default'))->merge(),
                'bar' => new MergeProp('bar value'),
            ],
            'app',
            '123'
        );
        $response = $response->toResponse($request);
        /** @var BaseResponse $response */
        $view = $response->getOriginalContent();
        $page = $view->getData()['page'];

        $this->assertInstanceOf(BaseResponse::class, $response);
        $this->assertInstanceOf(View::class, $view);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame([
            'default' => ['foo'],
        ], $page['deferredProps']);
        $this->assertSame([
            'foo',
            'bar',
        ], $page['mergeProps']);
        $this->assertFalse($page['clearHistory']);
        $this->assertFalse($page['encryptHistory']);
        $this->assertSame('<div id="app" data-page="{&quot;component&quot;:&quot;User\/Edit&quot;,&quot;props&quot;:{&quot;user&quot;:{&quot;name&quot;:&quot;Jonathan&quot;},&quot;bar&quot;:&quot;bar value&quot;},&quot;url&quot;:&quot;\/user\/123&quot;,&quot;version&quot;:&quot;123&quot;,&quot;clearHistory&quot;:false,&quot;encryptHistory&quot;:false,&quot;mergeProps&quot;:[&quot;foo&quot;,&quot;bar&quot;],&quot;deferredProps&quot;:{&quot;default&quot;:[&quot;foo&quot;]}}"></div>', $view->render());
    }

    public function test_server_response_with_defer_and_deep_merge_props(): void
    {
        $request = Request::create('/user/123', 'GET');

        $user = ['name' => 'Jonathan'];
        $response = new Response(
            'User/Edit',
            [
                'user' => $user,
                'foo' => (new DeferProp(function () {
                    return 'foo value';
                }, 'default'))->deepMerge(),
                'bar' => (new MergeProp('bar value'))->deepMerge(),
            ],
            'app',
            '123'
        );
        $response = $response->toResponse($request);
        /** @var BaseResponse $response */
        $view = $response->getOriginalContent();
        $page = $view->getData()['page'];

        $this->assertInstanceOf(BaseResponse::class, $response);
        $this->assertInstanceOf(View::class, $view);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame([
            'default' => ['foo'],
        ], $page['deferredProps']);
        $this->assertSame([
            'foo',
            'bar',
        ], $page['deepMergeProps']);
        $this->assertFalse($page['clearHistory']);
        $this->assertFalse($page['encryptHistory']);
        $this->assertSame('<div id="app" data-page="{&quot;component&quot;:&quot;User\/Edit&quot;,&quot;props&quot;:{&quot;user&quot;:{&quot;name&quot;:&quot;Jonathan&quot;},&quot;bar&quot;:&quot;bar value&quot;},&quot;url&quot;:&quot;\/user\/123&quot;,&quot;version&quot;:&quot;123&quot;,&quot;clearHistory&quot;:false,&quot;encryptHistory&quot;:false,&quot;deepMergeProps&quot;:[&quot;foo&quot;,&quot;bar&quot;],&quot;deferredProps&quot;:{&quot;default&quot;:[&quot;foo&quot;]}}"></div>', $view->render());
    }

    public function test_exclude_merge_props_from_partial_only_response(): void
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Partial-Component' => 'User/Edit']);
        $request->headers->add(['X-Inertia-Partial-Data' => 'user']);

        $user = ['name' => 'Jonathan'];
        $response = new Response(
            'User/Edit',
            [
                'user' => $user,
                'foo' => new MergeProp('foo value'),
                'bar' => new MergeProp('bar value'),
            ],
            'app',
            '123'
        );
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData();

        $props = get_object_vars($page->props);

        $this->assertInstanceOf(JsonResponse::class, $response);

        $this->assertSame('Jonathan', $props['user']->name);
        $this->assertArrayNotHasKey('foo', $props);
        $this->assertArrayNotHasKey('bar', $props);
        $this->assertFalse(isset($page->mergeProps));
    }

    public function test_exclude_merge_props_from_partial_except_response(): void
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Partial-Component' => 'User/Edit']);
        $request->headers->add(['X-Inertia-Partial-Except' => 'foo']);

        $user = ['name' => 'Jonathan'];
        $response = new Response(
            'User/Edit',
            [
                'user' => $user,
                'foo' => new MergeProp('foo value'),
                'bar' => new MergeProp('bar value'),
            ],
            'app',
            '123'
        );
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData();

        $props = get_object_vars($page->props);

        $this->assertInstanceOf(JsonResponse::class, $response);

        $this->assertSame('Jonathan', $props['user']->name);
        $this->assertArrayNotHasKey('foo', $props);
        $this->assertArrayHasKey('bar', $props);
        $this->assertSame(['bar'], $page->mergeProps);
    }

    public function test_exclude_merge_props_when_passed_in_reset_header(): void
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Partial-Component' => 'User/Edit']);
        $request->headers->add(['X-Inertia-Partial-Data' => 'foo']);
        $request->headers->add(['X-Inertia-Reset' => 'foo']);

        $user = ['name' => 'Jonathan'];
        $response = new Response(
            'User/Edit',
            [
                'user' => $user,
                'foo' => new MergeProp('foo value'),
                'bar' => new MergeProp('bar value'),
            ],
            'app',
            '123'
        );

        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData();

        $props = get_object_vars($page->props);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame($props['foo'], 'foo value');
        $this->assertArrayNotHasKey('bar', $props);
        $this->assertFalse(isset($page->mergeProps));
    }

    public function test_xhr_response(): void
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);

        $user = (object) ['name' => 'Jonathan'];
        $response = new Response('User/Edit', ['user' => $user], 'app', '123');
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('User/Edit', $page->component);
        $this->assertSame('Jonathan', $page->props->user->name);
        $this->assertSame('/user/123', $page->url);
        $this->assertSame('123', $page->version);
    }

    public function test_xhr_response_with_deferred_props_includes_deferred_metadata(): void
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);

        $response = new Response('User/Edit', [
            'user' => ['name' => 'Jonathan'],
            'results' => new DeferProp(fn () => ['data' => ['item1', 'item2']], 'default'),
        ], 'app', '123');
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('User/Edit', $page->component);
        $this->assertSame('Jonathan', $page->props->user->name);
        $this->assertFalse(property_exists($page->props, 'results'));
        $this->assertSame('/user/123', $page->url);
        $this->assertSame('123', $page->version);
        $this->assertEquals((object) ['default' => ['results']], $page->deferredProps);
    }

    public function test_resource_response(): void
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);

        $resource = new FakeResource(['name' => 'Jonathan']);

        $response = new Response('User/Edit', ['user' => $resource], 'app', '123');
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('User/Edit', $page->component);
        $this->assertSame('Jonathan', $page->props->user->name);
        $this->assertSame('/user/123', $page->url);
        $this->assertSame('123', $page->version);
    }

    public function test_lazy_callable_resource_response(): void
    {
        $request = Request::create('/users', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);

        $response = new Response('User/Index', [
            'users' => fn () => [['name' => 'Jonathan']],
            'organizations' => fn () => [['name' => 'Inertia']],
        ], 'app', '123');
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('User/Index', $page->component);
        $this->assertSame('/users', $page->url);
        $this->assertSame('123', $page->version);
        tap($page->props->users, function ($users) {
            $this->assertSame(json_encode([['name' => 'Jonathan']]), json_encode($users));
        });
        tap($page->props->organizations, function ($organizations) {
            $this->assertSame(json_encode([['name' => 'Inertia']]), json_encode($organizations));
        });
    }

    public function test_lazy_callable_resource_partial_response(): void
    {
        $request = Request::create('/users', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Partial-Data' => 'users']);
        $request->headers->add(['X-Inertia-Partial-Component' => 'User/Index']);

        $response = new Response('User/Index', [
            'users' => fn () => [['name' => 'Jonathan']],
            'organizations' => fn () => [['name' => 'Inertia']],
        ], 'app', '123');
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('User/Index', $page->component);
        $this->assertSame('/users', $page->url);
        $this->assertSame('123', $page->version);
        $this->assertFalse(property_exists($page->props, 'organizations'));
        tap($page->props->users, function ($users) {
            $this->assertSame(json_encode([['name' => 'Jonathan']]), json_encode($users));
        });
    }

    public function test_lazy_resource_response(): void
    {
        $request = Request::create('/users', 'GET', ['page' => 1]);
        $request->headers->add(['X-Inertia' => 'true']);

        $users = Collection::make([
            new Fluent(['name' => 'Jonathan']),
            new Fluent(['name' => 'Taylor']),
            new Fluent(['name' => 'Jeffrey']),
        ]);

        $callable = static function () use ($users) {
            $page = new LengthAwarePaginator($users->take(2), $users->count(), 2);

            return new class($page) extends ResourceCollection {};
        };

        $response = new Response('User/Index', ['users' => $callable], 'app', '123');
        /** @var JsonResponse $response */
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
        $this->assertSame('/users?page=1', $page->url);
        $this->assertSame('123', $page->version);
        tap($page->props->users, function ($users) use ($expected) {
            $this->assertSame(json_encode($expected['data']), json_encode($users->data));
            $this->assertSame(json_encode($expected['links']), json_encode($users->links));
            $this->assertSame('/', $users->meta->path);
        });
    }

    public function test_nested_lazy_resource_response(): void
    {
        $request = Request::create('/users', 'GET', ['page' => 1]);
        $request->headers->add(['X-Inertia' => 'true']);

        $users = Collection::make([
            new Fluent(['name' => 'Jonathan']),
            new Fluent(['name' => 'Taylor']),
            new Fluent(['name' => 'Jeffrey']),
        ]);

        $callable = static function () use ($users) {
            $page = new LengthAwarePaginator($users->take(2), $users->count(), 2);

            // nested array with ResourceCollection to resolve
            return [
                'users' => new class($page) extends ResourceCollection {},
            ];
        };

        $response = new Response('User/Index', ['something' => $callable], 'app', '123');
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData();

        $expected = [
            'users' => [
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
            ],
        ];

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('User/Index', $page->component);
        $this->assertSame('/users?page=1', $page->url);
        $this->assertSame('123', $page->version);
        tap($page->props->something->users, function ($users) use ($expected) {
            $this->assertSame(json_encode($expected['users']['data']), json_encode($users->data));
            $this->assertSame(json_encode($expected['users']['links']), json_encode($users->links));
            $this->assertSame('/', $users->meta->path);
        });
    }

    public function test_arrayable_prop_response(): void
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);

        $resource = FakeResource::make(['name' => 'Jonathan']);

        $response = new Response('User/Edit', ['user' => $resource], 'app', '123');
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('User/Edit', $page->component);
        $this->assertSame('Jonathan', $page->props->user->name);
        $this->assertSame('/user/123', $page->url);
        $this->assertSame('123', $page->version);
    }

    public function test_promise_props_are_resolved(): void
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);

        $user = (object) ['name' => 'Jonathan'];

        $promise = Mockery::mock('GuzzleHttp\Promise\PromiseInterface')
            ->shouldReceive('wait')
            ->andReturn($user)
            ->getMock();

        $response = new Response('User/Edit', ['user' => $promise], 'app', '123');
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('User/Edit', $page->component);
        $this->assertSame('Jonathan', $page->props->user->name);
        $this->assertSame('/user/123', $page->url);
        $this->assertSame('123', $page->version);
    }

    public function test_xhr_partial_response(): void
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Partial-Component' => 'User/Edit']);
        $request->headers->add(['X-Inertia-Partial-Data' => 'partial']);

        $user = (object) ['name' => 'Jonathan'];
        $response = new Response('User/Edit', ['user' => $user, 'partial' => 'partial-data'], 'app', '123');
        /** @var JsonResponse $response */
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

    public function test_exclude_props_from_partial_response(): void
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Partial-Component' => 'User/Edit']);
        $request->headers->add(['X-Inertia-Partial-Except' => 'user']);

        $user = (object) ['name' => 'Jonathan'];
        $response = new Response('User/Edit', ['user' => $user, 'partial' => 'partial-data'], 'app', '123');
        /** @var JsonResponse $response */
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

    public function test_nested_partial_props(): void
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Partial-Component' => 'User/Edit']);
        $request->headers->add(['X-Inertia-Partial-Data' => 'auth.user,auth.refresh_token']);

        $props = [
            'auth' => [
                'user' => new LazyProp(function () {
                    return [
                        'name' => 'Jonathan Reinink',
                        'email' => 'jonathan@example.com',
                    ];
                }),
                'refresh_token' => 'value',
                'token' => 'value',
            ],
            'shared' => [
                'flash' => 'value',
            ],
        ];

        $response = new Response('User/Edit', $props);
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertFalse(isset($page->props->shared));
        $this->assertFalse(isset($page->props->auth->token));
        $this->assertSame('Jonathan Reinink', $page->props->auth->user->name);
        $this->assertSame('jonathan@example.com', $page->props->auth->user->email);
        $this->assertSame('value', $page->props->auth->refresh_token);
    }

    public function test_exclude_nested_props_from_partial_response(): void
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Partial-Component' => 'User/Edit']);
        $request->headers->add(['X-Inertia-Partial-Data' => 'auth']);
        $request->headers->add(['X-Inertia-Partial-Except' => 'auth.user']);

        $props = [
            'auth' => [
                'user' => new LazyProp(function () {
                    return [
                        'name' => 'Jonathan Reinink',
                        'email' => 'jonathan@example.com',
                    ];
                }),
                'refresh_token' => 'value',
            ],
            'shared' => [
                'flash' => 'value',
            ],
        ];

        $response = new Response('User/Edit', $props);
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertFalse(isset($page->props->auth->user));
        $this->assertFalse(isset($page->props->shared));
        $this->assertSame('value', $page->props->auth->refresh_token);
    }

    public function test_lazy_props_are_not_included_by_default(): void
    {
        $request = Request::create('/users', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);

        $lazyProp = new LazyProp(function () {
            return 'A lazy value';
        });

        $response = new Response('Users', ['users' => [], 'lazy' => $lazyProp], 'app', '123');
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertSame([], $page->props->users);
        $this->assertFalse(property_exists($page->props, 'lazy'));
    }

    public function test_lazy_props_are_included_in_partial_reload(): void
    {
        $request = Request::create('/users', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Partial-Component' => 'Users']);
        $request->headers->add(['X-Inertia-Partial-Data' => 'lazy']);

        $lazyProp = new LazyProp(function () {
            return 'A lazy value';
        });

        $response = new Response('Users', ['users' => [], 'lazy' => $lazyProp], 'app', '123');
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertFalse(property_exists($page->props, 'users'));
        $this->assertSame('A lazy value', $page->props->lazy);
    }

    public function test_defer_arrayable_props_are_resolved_in_partial_reload(): void
    {
        $request = Request::create('/users', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Partial-Component' => 'Users']);
        $request->headers->add(['X-Inertia-Partial-Data' => 'defer']);

        $deferProp = new DeferProp(function () {
            return new class implements Arrayable
            {
                public function toArray()
                {
                    return ['foo' => 'bar'];
                }
            };
        });

        $response = new Response('Users', ['users' => [], 'defer' => $deferProp], 'app', '123');
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertFalse(property_exists($page->props, 'users'));
        $this->assertEquals((object) ['foo' => 'bar'], $page->props->defer);
    }

    public function test_always_props_are_included_on_partial_reload(): void
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Partial-Component' => 'User/Edit']);
        $request->headers->add(['X-Inertia-Partial-Data' => 'data']);

        $props = [
            'user' => new LazyProp(function () {
                return [
                    'name' => 'Jonathan Reinink',
                    'email' => 'jonathan@example.com',
                ];
            }),
            'data' => [
                'name' => 'Taylor Otwell',
            ],
            'errors' => new AlwaysProp(function () {
                return [
                    'name' => 'The email field is required.',
                ];
            }),
        ];

        $response = new Response('User/Edit', $props, 'app', '123');
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertSame('The email field is required.', $page->props->errors->name);
        $this->assertSame('Taylor Otwell', $page->props->data->name);
        $this->assertFalse(isset($page->props->user));
    }

    public function test_string_function_names_are_not_invoked_as_callables(): void
    {
        $request = Request::create('/user/123', 'GET');

        $response = new Response('User/Edit', [
            'always' => new AlwaysProp('date'),
            'merge' => new MergeProp('trim'),
        ], 'app', '123');

        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getOriginalContent()->getData()['page'];

        $this->assertSame('date', $page['props']['always']);
        $this->assertSame('trim', $page['props']['merge']);
    }

    public function test_inertia_responsable_objects(): void
    {
        $request = Request::create('/user/123', 'GET');

        $response = new Response('User/Edit', [
            'foo' => 'bar',
            new class implements ProvidesInertiaProperties
            {
                /**
                 * @return Collection<string, string>
                 */
                public function toInertiaProperties(RenderContext $context): iterable
                {
                    return collect([
                        'baz' => 'qux',
                    ]);
                }
            },
            'quux' => 'corge',

        ], 'app', '123');
        /** @var BaseResponse $response */
        $response = $response->toResponse($request);
        $view = $response->getOriginalContent();
        $page = $view->getData()['page'];

        $this->assertSame('bar', $page['props']['foo']);
        $this->assertSame('qux', $page['props']['baz']);
        $this->assertSame('corge', $page['props']['quux']);
    }

    public function test_inertia_response_type_prop(): void
    {
        $request = Request::create('/user/123', 'GET');

        Inertia::share('items', ['foo']);
        Inertia::share('deep.foo.bar', ['foo']);

        $response = new Response('User/Edit', [
            'items' => new MergeWithSharedProp(['bar']),
            'deep' => [
                'foo' => [
                    'bar' => new MergeWithSharedProp(['baz']),
                ],
            ],
        ], 'app', '123');
        /** @var BaseResponse $response */
        $response = $response->toResponse($request);
        $view = $response->getOriginalContent();
        $page = $view->getData()['page'];

        $this->assertSame(['foo', 'bar'], $page['props']['items']);
        $this->assertSame(['foo', 'baz'], $page['props']['deep']['foo']['bar']);
    }

    public function test_top_level_dot_props_get_unpacked(): void
    {
        $props = [
            'auth' => [
                'user' => [
                    'name' => 'Jonathan Reinink',
                ],
            ],
            'auth.user.can' => [
                'do.stuff' => true,
            ],
            'product' => ['name' => 'My example product'],
        ];

        $request = Request::create('/products/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);

        $response = new Response('User/Edit', $props, 'app', '123');
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData(true);

        $user = $page['props']['auth']['user'];
        $this->assertSame('Jonathan Reinink', $user['name']);
        $this->assertTrue($user['can']['do.stuff']);
        $this->assertFalse(array_key_exists('auth.user.can', $page['props']));
    }

    public function test_nested_dot_props_do_not_get_unpacked(): void
    {
        $props = [
            'auth' => [
                'user.can' => [
                    'do.stuff' => true,
                ],
                'user' => [
                    'name' => 'Jonathan Reinink',
                ],
            ],
            'product' => ['name' => 'My example product'],
        ];

        $request = Request::create('/products/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);

        $response = new Response('User/Edit', $props, 'app', '123');
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData(true);

        $auth = $page['props']['auth'];
        $this->assertSame('Jonathan Reinink', $auth['user']['name']);
        $this->assertTrue($auth['user.can']['do.stuff']);
        $this->assertFalse(array_key_exists('can', $auth));
    }

    public function test_props_can_be_added_using_the_with_method(): void
    {
        $request = Request::create('/user/123', 'GET');
        $response = new Response('User/Edit', [], 'app', '123');

        $response->with(['foo' => 'bar', 'baz' => 'qux'])
            ->with(['quux' => 'corge'])
            ->with(new class implements ProvidesInertiaProperties
            {
                /**
                 * @return Collection<string, string>
                 */
                public function toInertiaProperties(RenderContext $context): iterable
                {
                    return collect(['grault' => 'garply']);
                }
            });

        /** @var BaseResponse $response */
        $response = $response->toResponse($request);
        $view = $response->getOriginalContent();
        $page = $view->getData()['page'];

        $this->assertSame('bar', $page['props']['foo']);
        $this->assertSame('qux', $page['props']['baz']);
        $this->assertSame('corge', $page['props']['quux']);
    }

    public function test_once_props_are_always_resolved_on_initial_page_load(): void
    {
        $request = Request::create('/user/123', 'GET');

        $response = new Response('User/Edit', ['foo' => Inertia::once(fn () => 'bar')], 'app', '123');
        /** @var BaseResponse $response */
        $response = $response->toResponse($request);
        $view = $response->getOriginalContent();
        $page = $view->getData()['page'];

        $this->assertInstanceOf(BaseResponse::class, $response);
        $this->assertInstanceOf(View::class, $view);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('bar', $page['props']['foo']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertFalse($page['clearHistory']);
        $this->assertFalse($page['encryptHistory']);
        $this->assertSame(['foo' => ['prop' => 'foo', 'expiresAt' => null]], $page['onceProps']);
        $this->assertSame('<div id="app" data-page="{&quot;component&quot;:&quot;User\/Edit&quot;,&quot;props&quot;:{&quot;foo&quot;:&quot;bar&quot;},&quot;url&quot;:&quot;\/user\/123&quot;,&quot;version&quot;:&quot;123&quot;,&quot;clearHistory&quot;:false,&quot;encryptHistory&quot;:false,&quot;onceProps&quot;:{&quot;foo&quot;:{&quot;prop&quot;:&quot;foo&quot;,&quot;expiresAt&quot;:null}}}"></div>', $view->render());
    }

    public function test_fresh_once_props_are_included_on_initial_page_load(): void
    {
        $request = Request::create('/user/123', 'GET');

        $response = new Response('User/Edit', ['foo' => Inertia::once(fn () => 'bar')->fresh()], 'app', '123');
        /** @var BaseResponse $response */
        $response = $response->toResponse($request);
        $view = $response->getOriginalContent();
        $page = $view->getData()['page'];

        $this->assertInstanceOf(BaseResponse::class, $response);
        $this->assertInstanceOf(View::class, $view);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('bar', $page['props']['foo']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertArrayHasKey('onceProps', $page);
        $this->assertSame(['foo' => ['prop' => 'foo', 'expiresAt' => null]], $page['onceProps']);
    }

    public function test_once_props_are_resolved_with_a_custom_key_and_ttl_value(): void
    {
        $this->freezeSecond();

        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);

        $response = new Response('User/Edit', [
            'foo' => Inertia::once(fn () => 'bar')->as('baz')->until(now()->addMinute()),
        ], 'app', '123');
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertInstanceOf(JsonResponse::class, $response);

        $this->assertSame('User/Edit', $page->component);
        $this->assertSame('bar', $page->props->foo);
        $this->assertSame('/user/123', $page->url);
        $this->assertSame('123', $page->version);
        $this->assertEquals((object) ['baz' => (object) ['prop' => 'foo', 'expiresAt' => now()->addMinute()->getTimestampMs()]], $page->onceProps);
    }

    public function test_once_props_are_not_resolved_on_subsequent_requests_when_they_are_in_the_once_props_header(): void
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Except-Once-Props' => 'foo']);

        $response = new Response('User/Edit', ['foo' => Inertia::once(fn () => 'bar')], 'app', '123');
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertInstanceOf(JsonResponse::class, $response);

        $this->assertSame('User/Edit', $page->component);
        $this->assertArrayNotHasKey('foo', (array) $page->props);
        $this->assertSame('/user/123', $page->url);
        $this->assertSame('123', $page->version);
        $this->assertEquals((object) ['foo' => (object) ['prop' => 'foo', 'expiresAt' => null]], $page->onceProps);
    }

    public function test_once_props_are_resolved_on_subsequent_requests_when_the_once_props_header_is_missing(): void
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);

        $response = new Response('User/Edit', ['foo' => Inertia::once(fn () => 'bar')], 'app', '123');
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertInstanceOf(JsonResponse::class, $response);

        $this->assertSame('User/Edit', $page->component);
        $this->assertSame('bar', $page->props->foo);
        $this->assertSame('/user/123', $page->url);
        $this->assertSame('123', $page->version);
        $this->assertEquals((object) ['foo' => (object) ['prop' => 'foo', 'expiresAt' => null]], $page->onceProps);
    }

    public function test_once_props_are_resolved_on_subsequent_requests_when_they_are_not_in_the_once_props_header(): void
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Except-Once-Props' => 'baz']);

        $response = new Response('User/Edit', ['foo' => Inertia::once(fn () => 'bar')], 'app', '123');
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertInstanceOf(JsonResponse::class, $response);

        $this->assertSame('User/Edit', $page->component);
        $this->assertSame('bar', $page->props->foo);
        $this->assertSame('/user/123', $page->url);
        $this->assertSame('123', $page->version);
        $this->assertEquals((object) ['foo' => (object) ['prop' => 'foo', 'expiresAt' => null]], $page->onceProps);
    }

    public function test_once_props_are_resolved_on_partial_requests_without_only_or_except(): void
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Partial-Component' => 'User/Edit']);
        $request->headers->add(['X-Inertia-Partial-Data' => 'foo']);
        $request->headers->add(['X-Inertia-Except-Once-Props' => 'foo']);

        $response = new Response('User/Edit', ['foo' => Inertia::once(fn () => 'bar')], 'app', '123');
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertInstanceOf(JsonResponse::class, $response);

        $this->assertSame('User/Edit', $page->component);
        $this->assertSame('bar', $page->props->foo);
        $this->assertSame('/user/123', $page->url);
        $this->assertSame('123', $page->version);
        $this->assertEquals((object) ['foo' => (object) ['prop' => 'foo', 'expiresAt' => null]], $page->onceProps);
    }

    public function test_once_props_are_resolved_on_partial_requests_when_included_in_only_headers(): void
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Partial-Component' => 'User/Edit']);
        $request->headers->add(['X-Inertia-Partial-Data' => 'foo']);
        $request->headers->add(['X-Inertia-Except-Once-Props' => 'foo']);

        $response = new Response('User/Edit', [
            'foo' => Inertia::once(fn () => 'bar'),
            'baz' => Inertia::once(fn () => 'qux'),
        ], 'app', '123');
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertInstanceOf(JsonResponse::class, $response);

        $this->assertSame('User/Edit', $page->component);
        $this->assertSame('bar', $page->props->foo);
        $this->assertFalse(isset($page->props->baz));
        $this->assertSame('/user/123', $page->url);
        $this->assertSame('123', $page->version);
        $this->assertEquals((object) [
            'foo' => (object) ['prop' => 'foo', 'expiresAt' => null],
        ], $page->onceProps);
    }

    public function test_once_props_are_not_resolved_on_partial_requests_when_excluded_in_except_headers(): void
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Partial-Component' => 'User/Edit']);
        $request->headers->add(['X-Inertia-Partial-Except' => 'foo']);
        $request->headers->add(['X-Inertia-Except-Once-Props' => 'foo']);

        $response = new Response('User/Edit', [
            'foo' => Inertia::once(fn () => 'bar'),
            'baz' => Inertia::once(fn () => 'qux'),
        ], 'app', '123');
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertInstanceOf(JsonResponse::class, $response);

        $this->assertSame('User/Edit', $page->component);
        $this->assertFalse(isset($page->props->foo));
        $this->assertSame('qux', $page->props->baz);
        $this->assertSame('/user/123', $page->url);
        $this->assertSame('123', $page->version);
        $this->assertEquals((object) [
            'baz' => (object) ['prop' => 'baz', 'expiresAt' => null],
        ], $page->onceProps);
    }

    public function test_fresh_props_are_resolved_even_when_in_except_once_props_header(): void
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Except-Once-Props' => 'foo']);

        $response = new Response('User/Edit', ['foo' => Inertia::once(fn () => 'bar')->fresh()], 'app', '123');
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertInstanceOf(JsonResponse::class, $response);

        $this->assertSame('User/Edit', $page->component);
        $this->assertSame('bar', $page->props->foo);
        $this->assertSame('/user/123', $page->url);
        $this->assertSame('123', $page->version);
        $this->assertEquals((object) [
            'foo' => (object) ['prop' => 'foo', 'expiresAt' => null],
        ], $page->onceProps);
    }

    public function test_fresh_props_are_not_excluded_while_once_props_are_excluded(): void
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Except-Once-Props' => 'foo,baz']);

        $response = new Response('User/Edit', [
            'foo' => Inertia::once(fn () => 'bar')->fresh(),
            'baz' => Inertia::once(fn () => 'qux'),
        ], 'app', '123');
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertInstanceOf(JsonResponse::class, $response);

        $this->assertSame('User/Edit', $page->component);
        $this->assertSame('bar', $page->props->foo);
        $this->assertFalse(isset($page->props->baz));
        $this->assertSame('/user/123', $page->url);
        $this->assertSame('123', $page->version);
        $this->assertEquals((object) [
            'foo' => (object) ['prop' => 'foo', 'expiresAt' => null],
            'baz' => (object) ['prop' => 'baz', 'expiresAt' => null],
        ], $page->onceProps);
    }

    public function test_defer_props_that_are_once_and_already_loaded_are_excluded(): void
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Except-Once-Props' => 'defer']);

        $response = new Response('User/Edit', [
            'defer' => Inertia::defer(fn () => 'value')->once(),
        ], 'app', '123');
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertInstanceOf(JsonResponse::class, $response);

        $this->assertSame('User/Edit', $page->component);
        $this->assertFalse(isset($page->props->defer));
        $this->assertFalse(isset($page->deferredProps));
        $this->assertSame('/user/123', $page->url);
        $this->assertSame('123', $page->version);
        $this->assertEquals((object) [
            'defer' => (object) ['prop' => 'defer', 'expiresAt' => null],
        ], $page->onceProps);
    }

    public function test_defer_props_that_are_once_and_already_loaded_not_excluded_when_explicitly_requested(): void
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Partial-Component' => 'User/Edit']);
        $request->headers->add(['X-Inertia-Partial-Data' => 'defer']);
        $request->headers->add(['X-Inertia-Except-Once-Props' => 'defer']);

        $response = new Response('User/Edit', [
            'defer' => Inertia::defer(fn () => 'value')->once(),
        ], 'app', '123');
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertInstanceOf(JsonResponse::class, $response);

        $this->assertSame('User/Edit', $page->component);
        $this->assertSame('value', $page->props->defer);
        $this->assertSame('/user/123', $page->url);
        $this->assertSame('123', $page->version);
        $this->assertFalse(isset($page->deferredProps));
        $this->assertEquals((object) [
            'defer' => (object) ['prop' => 'defer', 'expiresAt' => null],
        ], $page->onceProps);
    }

    public function test_responsable_with_invalid_key(): void
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);

        $resource = new FakeResource(["\x00*\x00_invalid_key" => 'for object']);

        $response = new Response('User/Edit', ['resource' => $resource], 'app', '123');
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData(true);

        $this->assertSame(
            ["\x00*\x00_invalid_key" => 'for object'],
            $page['props']['resource']
        );
    }

    public function test_the_page_url_is_prefixed_with_the_proxy_prefix(): void
    {
        Request::setTrustedProxies(['1.2.3.4'], Request::HEADER_X_FORWARDED_PREFIX);

        $request = Request::create('/user/123', 'GET');
        $request->server->set('REMOTE_ADDR', '1.2.3.4');
        $request->headers->set('X_FORWARDED_PREFIX', '/sub/directory');

        $user = ['name' => 'Jonathan'];
        $response = new Response('User/Edit', ['user' => $user], 'app', '123');
        /** @var BaseResponse $response */
        $response = $response->toResponse($request);
        $view = $response->getOriginalContent();
        $page = $view->getData()['page'];

        $this->assertInstanceOf(BaseResponse::class, $response);
        $this->assertInstanceOf(View::class, $view);

        $this->assertSame('/sub/directory/user/123', $page['url']);
    }

    public function test_the_page_url_doesnt_double_up(): void
    {
        $request = Request::create('/subpath/product/123', 'GET', [], [], [], [
            'SCRIPT_FILENAME' => '/project/public/index.php',
            'SCRIPT_NAME' => '/subpath/index.php',
        ]);
        $request->headers->add(['X-Inertia' => 'true']);

        $response = new Response('Product/Show', []);
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertSame('/subpath/product/123', $page->url);
    }

    public function test_trailing_slashes_in_a_url_are_preserved(): void
    {
        $request = Request::create('/users/', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);

        $response = new Response('User/Index', []);
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertSame('/users/', $page->url);
    }

    public function test_trailing_slashes_in_a_url_with_query_parameters_are_preserved(): void
    {
        $request = Request::create('/users/?page=1&sort=name', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);

        $response = new Response('User/Index', []);
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertSame('/users/?page=1&sort=name', $page->url);
    }

    public function test_a_url_without_trailing_slash_is_resolved_correctly(): void
    {
        $request = Request::create('/users', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);

        $response = new Response('User/Index', []);
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertSame('/users', $page->url);
    }

    public function test_a_url_without_trailing_slash_and_query_parameters_is_resolved_correctly(): void
    {
        $request = Request::create('/users?page=1&sort=name', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);

        $response = new Response('User/Index', []);
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertSame('/users?page=1&sort=name', $page->url);
    }

    public function test_deferred_props_from_provides_inertia_properties_are_included_in_deferred_props_metadata(): void
    {
        $request = Request::create('/user/123', 'GET');

        $response = new Response('User/Edit', [
            'user' => ['name' => 'Jonathan'],
            new class implements ProvidesInertiaProperties
            {
                public function toInertiaProperties(RenderContext $context): iterable
                {
                    return [
                        'foo' => new DeferProp(fn () => 'bar', 'default'),
                    ];
                }
            },
        ], 'app', '123');
        /** @var BaseResponse $response */
        $response = $response->toResponse($request);
        $view = $response->getOriginalContent();
        $page = $view->getData()['page'];

        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertArrayNotHasKey('foo', $page['props']);
        $this->assertSame([
            'default' => ['foo'],
        ], $page['deferredProps']);
    }

    public function test_deferred_props_from_provides_inertia_properties_with_multiple_groups(): void
    {
        $request = Request::create('/user/123', 'GET');

        $response = new Response('User/Edit', [
            'user' => ['name' => 'Jonathan'],
            new class implements ProvidesInertiaProperties
            {
                public function toInertiaProperties(RenderContext $context): iterable
                {
                    return [
                        'foo' => new DeferProp(fn () => 'foo value', 'default'),
                        'bar' => new DeferProp(fn () => 'bar value', 'custom'),
                    ];
                }
            },
        ], 'app', '123');
        /** @var BaseResponse $response */
        $response = $response->toResponse($request);
        $view = $response->getOriginalContent();
        $page = $view->getData()['page'];

        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertArrayNotHasKey('foo', $page['props']);
        $this->assertArrayNotHasKey('bar', $page['props']);
        $this->assertSame([
            'default' => ['foo'],
            'custom' => ['bar'],
        ], $page['deferredProps']);
    }

    public function test_deferred_props_from_provides_inertia_properties_can_be_loaded_via_partial_request(): void
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Partial-Component' => 'User/Edit']);
        $request->headers->add(['X-Inertia-Partial-Data' => 'foo']);

        $response = new Response('User/Edit', [
            'user' => ['name' => 'Jonathan'],
            new class implements ProvidesInertiaProperties
            {
                public function toInertiaProperties(RenderContext $context): iterable
                {
                    return [
                        'foo' => new DeferProp(fn () => 'bar', 'default'),
                    ];
                }
            },
        ], 'app', '123');
        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData(true);

        $this->assertSame('bar', $page['props']['foo']);
        $this->assertArrayNotHasKey('user', $page['props']);
    }

    public function test_merge_props_from_provides_inertia_properties_are_included_in_merge_props_metadata(): void
    {
        $request = Request::create('/user/123', 'GET');

        $response = new Response('User/Edit', [
            'user' => ['name' => 'Jonathan'],
            new class implements ProvidesInertiaProperties
            {
                public function toInertiaProperties(RenderContext $context): iterable
                {
                    return [
                        'foo' => new MergeProp('foo value'),
                    ];
                }
            },
        ], 'app', '123');
        /** @var BaseResponse $response */
        $response = $response->toResponse($request);
        $view = $response->getOriginalContent();
        $page = $view->getData()['page'];

        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('foo value', $page['props']['foo']);
        $this->assertSame(['foo'], $page['mergeProps']);
    }

    public function test_once_props_from_provides_inertia_properties_are_included_in_once_props_metadata(): void
    {
        $request = Request::create('/user/123', 'GET');

        $response = new Response('User/Edit', [
            'user' => ['name' => 'Jonathan'],
            new class implements ProvidesInertiaProperties
            {
                public function toInertiaProperties(RenderContext $context): iterable
                {
                    return [
                        'foo' => Inertia::once(fn () => 'bar'),
                    ];
                }
            },
        ], 'app', '123');
        /** @var BaseResponse $response */
        $response = $response->toResponse($request);
        $view = $response->getOriginalContent();
        $page = $view->getData()['page'];

        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('bar', $page['props']['foo']);
        $this->assertSame(['foo' => ['prop' => 'foo', 'expiresAt' => null]], $page['onceProps']);
    }

    public function test_deferred_merge_props_from_provides_inertia_properties_include_both_metadata(): void
    {
        $request = Request::create('/user/123', 'GET');

        $response = new Response('User/Edit', [
            'user' => ['name' => 'Jonathan'],
            new class implements ProvidesInertiaProperties
            {
                public function toInertiaProperties(RenderContext $context): iterable
                {
                    return [
                        'foo' => (new DeferProp(fn () => 'foo value', 'default'))->merge(),
                    ];
                }
            },
        ], 'app', '123');
        /** @var BaseResponse $response */
        $response = $response->toResponse($request);
        $view = $response->getOriginalContent();
        $page = $view->getData()['page'];

        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertArrayNotHasKey('foo', $page['props']);
        $this->assertSame([
            'default' => ['foo'],
        ], $page['deferredProps']);
        $this->assertSame(['foo'], $page['mergeProps']);
    }
}
