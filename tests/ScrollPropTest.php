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
            'queryParam' => 'page',
            'previousPage' => null,
            'nextPage' => 2,
            'currentPage' => 1,
            'hasPreviousPage' => false,
            'hasNextPage' => true,
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

        $this->assertEquals('usersPage', $meta['queryParam']);
    }

    public function test_can_set_the_merge_strategy_based_on_the_scroll_direction_header(): void
    {
        $users = User::query()->paginate(15);

        // Test append strategy without header
        $appendProp = new ScrollProp($users);
        $appendProp->configureMergeDirection();
        $this->assertContains('data', $appendProp->appendsAtPaths());
        $this->assertEmpty($appendProp->prependsAtPaths());

        // Test append strategy with header set to 'down'
        request()->headers->set(Header::SCROLL_DIRECTION, 'down');
        $appendProp = new ScrollProp($users);
        $appendProp->configureMergeDirection();
        $this->assertContains('data', $appendProp->appendsAtPaths());
        $this->assertEmpty($appendProp->prependsAtPaths());

        // Test prepend strategy
        request()->headers->set(Header::SCROLL_DIRECTION, 'up');
        $prependProp = new ScrollProp($users);
        $prependProp->configureMergeDirection();
        $this->assertContains('data', $prependProp->prependsAtPaths());
        $this->assertEmpty($prependProp->appendsAtPaths());
    }
}
