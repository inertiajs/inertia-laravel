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
    }
}
