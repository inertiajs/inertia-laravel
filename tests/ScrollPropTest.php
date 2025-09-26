<?php

namespace Inertia\Tests;

use Inertia\ProvidesScrollMetadata;
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

                public function getPreviousPage(): int|string|null
                {
                    return 5;
                }

                public function getNextPage(): int|string|null
                {
                    return 7;
                }

                public function getCurrentPage(): int|string|null
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
}
