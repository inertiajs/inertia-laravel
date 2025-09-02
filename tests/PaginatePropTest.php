<?php

namespace Inertia\Tests;

use Inertia\PaginateProp;
use Inertia\PaginatorMeta;
use Inertia\Tests\Stubs\User;

class PaginatePropTest extends TestCase
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
        $paginateProp = new PaginateProp($users);

        $meta = $paginateProp->meta();

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

        $paginateProp = new PaginateProp($users, 'data', $customMetaCallback);

        $meta = $paginateProp->meta();

        $this->assertEquals('usersPage', $meta['queryParam']);
    }

    public function test_can_set_the_merge_strategy(): void
    {
        $users = User::query()->paginate(15);

        // Test append strategy
        $appendProp = new PaginateProp($users);
        $appendProp->setMergeStrategy(true);
        $this->assertContains('data', $appendProp->appendPaths());
        $this->assertEmpty($appendProp->prependPaths());

        // Test prepend strategy
        $prependProp = new PaginateProp($users);
        $prependProp->setMergeStrategy(false);
        $this->assertContains('data', $prependProp->prependPaths());
        $this->assertEmpty($prependProp->appendPaths());
    }
}
