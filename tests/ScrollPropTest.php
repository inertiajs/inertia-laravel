<?php

namespace Inertia\Tests;

use Inertia\PaginatorMeta;
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

        $meta = $scrollProp->meta();

        $this->assertEquals([
            'pageName' => 'page',
            'previousPage' => null,
            'nextPage' => 2,
            'currentPage' => 1,
        ], $meta);
    }

    public function test_resolves_custom_meta_data(): void
    {
        $users = User::query()->paginate(15);

        $customMetaCallback = function ($paginator) use ($users) {
            $this->assertEquals($users, $paginator);

            return new PaginatorMeta('usersPage');
        };

        $scrollProp = new ScrollProp($users, 'data', $customMetaCallback);

        $meta = $scrollProp->meta();

        $this->assertEquals('usersPage', $meta['pageName']);
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
