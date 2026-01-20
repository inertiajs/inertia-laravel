<?php

namespace Inertia\Tests;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as BaseResponse;
use Inertia\ProvidesScrollMetadata;
use Inertia\Response;
use Inertia\ScrollProp;
use Inertia\Support\Header;
use Inertia\Tests\Stubs\User;

class ScrollPropTest extends TestCase
{
    use InteractsWithUserModels;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpInteractsWithUserModels();
    }

    public function test_resolves_meta_data(): void
    {
        $users = User::query()->paginate(15);
        $scrollProp = new ScrollProp($users);

        $metadata = $scrollProp->metadata();

        $this->assertEquals([
            'pageName' => 'page',
            'previousPage' => null,
            'nextPage' => 2,
            'currentPage' => 1,
        ], $metadata);
    }

    public function test_resolves_custom_meta_data(): void
    {
        $users = User::query()->paginate(15);

        $customMetaCallback = fn () => new class implements ProvidesScrollMetadata
        {
            public function getPageName(): string
            {
                return 'usersPage';
            }

            public function getPreviousPage(): int
            {
                return 10;
            }

            public function getNextPage(): int
            {
                return 12;
            }

            public function getCurrentPage(): int
            {
                return 11;
            }
        };

        $scrollProp = new ScrollProp($users, 'data', $customMetaCallback);

        $metadata = $scrollProp->metadata();

        $this->assertEquals([
            'pageName' => 'usersPage',
            'previousPage' => 10,
            'nextPage' => 12,
            'currentPage' => 11,
        ], $metadata);
    }

    public function test_can_set_the_merge_intent_based_on_the_merge_intent_header(): void
    {
        $users = User::query()->paginate(15);

        // Test append intent without header
        $appendProp = new ScrollProp($users);
        $appendProp->configureMergeIntent();
        $this->assertContains('data', $appendProp->appendsAtPaths());
        $this->assertEmpty($appendProp->prependsAtPaths());

        // Test append intent with header set to 'down'
        request()->headers->set(Header::INFINITE_SCROLL_MERGE_INTENT, 'append');
        $appendProp = new ScrollProp($users);
        $appendProp->configureMergeIntent();
        $this->assertContains('data', $appendProp->appendsAtPaths());
        $this->assertEmpty($appendProp->prependsAtPaths());

        // Test prepend intent
        request()->headers->set(Header::INFINITE_SCROLL_MERGE_INTENT, 'prepend');
        $prependProp = new ScrollProp($users);
        $prependProp->configureMergeIntent();
        $this->assertContains('data', $prependProp->prependsAtPaths());
        $this->assertEmpty($prependProp->appendsAtPaths());

        // Test prepend intent with custom wrapper
        request()->headers->set(Header::INFINITE_SCROLL_MERGE_INTENT, 'prepend');
        $prependProp = new ScrollProp($users, 'items');
        $prependProp->configureMergeIntent();
        $this->assertContains('items', $prependProp->prependsAtPaths());
        $this->assertEmpty($prependProp->appendsAtPaths());
    }

    public function test_resolves_meta_data_with_callable_provider(): void
    {
        $callableMetadata = function () {
            return new class implements ProvidesScrollMetadata
            {
                public function getPageName(): string
                {
                    return 'callablePage';
                }

                public function getPreviousPage(): int
                {
                    return 5;
                }

                public function getNextPage(): int
                {
                    return 7;
                }

                public function getCurrentPage(): int
                {
                    return 6;
                }
            };
        };

        $scrollProp = new ScrollProp([], 'data', $callableMetadata);

        $metadata = $scrollProp->metadata();

        $this->assertEquals([
            'pageName' => 'callablePage',
            'previousPage' => 5,
            'nextPage' => 7,
            'currentPage' => 6,
        ], $metadata);
    }

    public function test_scroll_prop_value_is_resolved_only_once(): void
    {
        $callCount = 0;

        $scrollProp = new ScrollProp(function () use (&$callCount) {
            $callCount++;

            return ['item1', 'item2', 'item3'];
        });

        // Call the scroll prop multiple times
        $value1 = $scrollProp();
        $value2 = $scrollProp();
        $value3 = $scrollProp();

        // Verify the callback was only called once
        $this->assertEquals(1, $callCount, 'Scroll prop value callback should only be executed once');

        // Verify all calls return the same result
        $this->assertEquals($value1, $value2);
        $this->assertEquals($value2, $value3);
        $this->assertEquals(['item1', 'item2', 'item3'], $value1);
    }

    public function test_string_function_names_are_not_invoked(): void
    {
        $scrollProp = new ScrollProp('date');

        $this->assertSame('date', $scrollProp());
    }

    public function test_deferred_scroll_prop_is_excluded_from_initial_request(): void
    {
        $request = Request::create('/users', 'GET');

        $response = new Response(
            'Users/Index',
            [
                'users' => (new ScrollProp(fn () => User::query()->paginate(15)))->defer(),
            ],
            'app',
            '123'
        );

        /** @var BaseResponse $response */
        $response = $response->toResponse($request);
        $view = $response->getOriginalContent();
        $page = $view->getData()['page'];

        $this->assertArrayNotHasKey('users', $page['props']);
        $this->assertSame(['default' => ['users']], $page['deferredProps']);
        $this->assertArrayNotHasKey('scrollProps', $page);
    }

    public function test_deferred_scroll_prop_is_resolved_on_partial_request(): void
    {
        $request = Request::create('/users', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Partial-Component' => 'Users/Index']);
        $request->headers->add(['X-Inertia-Partial-Data' => 'users']);

        $response = new Response(
            'Users/Index',
            [
                'users' => (new ScrollProp(fn () => User::query()->paginate(15)))->defer(),
            ],
            'app',
            '123'
        );

        /** @var JsonResponse $response */
        $response = $response->toResponse($request);
        $page = $response->getData();

        $this->assertObjectHasProperty('users', $page->props);
        $this->assertCount(15, $page->props->users->data);
        $this->assertObjectHasProperty('scrollProps', $page);
        $this->assertEquals('page', $page->scrollProps->users->pageName);
        $this->assertContains('users.data', $page->mergeProps);
    }

    public function test_deferred_scroll_prop_can_have_custom_group(): void
    {
        $request = Request::create('/users', 'GET');

        $response = new Response(
            'Users/Index',
            [
                'users' => (new ScrollProp(fn () => User::query()->paginate(15)))->defer('custom-group'),
            ],
            'app',
            '123'
        );

        /** @var BaseResponse $response */
        $response = $response->toResponse($request);
        $view = $response->getOriginalContent();
        $page = $view->getData()['page'];

        $this->assertArrayNotHasKey('users', $page['props']);
        $this->assertSame(['custom-group' => ['users']], $page['deferredProps']);
    }
}
