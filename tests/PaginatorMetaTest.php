<?php

namespace Inertia\Tests;

use Illuminate\Support\Facades\DB;
use Inertia\PaginatorMeta;
use Inertia\Tests\Stubs\User;
use Inertia\Tests\Stubs\UserResource;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;

class PaginatorMetaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create 'users' table and seed 40 users
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::statement('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT)');
        DB::table('users')->insert(array_fill(0, 40, ['id' => null]));
    }

    public static function wrappedOrUnwrappedProvider()
    {
        return [
            'wrapped in http resource' => [true],
            'not wrapped in http resource' => [false],
        ];
    }

    #[DataProvider('wrappedOrUnwrappedProvider')]
    public function test_extract_metadata_from_simple_paginator(bool $wrappedinHttpResource)
    {
        $users = User::query()->simplePaginate(15);

        if ($wrappedinHttpResource) {
            $users = UserResource::collection($users);
        }

        $this->assertEquals([
            'queryParam' => 'page',
            'previousPage' => null,
            'nextPage' => 2,
            'currentPage' => 1,
            'hasPreviousPage' => false,
            'hasNextPage' => true,
        ], PaginatorMeta::from($users)->toArray());

        request()->merge(['page' => 2]);
        $users = User::query()->simplePaginate(15);

        $this->assertEquals([
            'queryParam' => 'page',
            'previousPage' => 1,
            'nextPage' => 3,
            'currentPage' => 2,
            'hasPreviousPage' => true,
            'hasNextPage' => true,
        ], PaginatorMeta::from($users)->toArray());

        request()->merge(['page' => 3]);
        $users = User::query()->simplePaginate(15);

        $this->assertEquals([
            'queryParam' => 'page',
            'previousPage' => 2,
            'nextPage' => null,
            'currentPage' => 3,
            'hasPreviousPage' => true,
            'hasNextPage' => false,
        ], PaginatorMeta::from($users)->toArray());
    }

    #[DataProvider('wrappedOrUnwrappedProvider')]
    public function test_extract_metadata_from_length_aware_paginator(bool $wrappedinHttpResource)
    {
        $users = User::query()->paginate(15);

        if ($wrappedinHttpResource) {
            $users = UserResource::collection($users);
        }

        $this->assertEquals([
            'queryParam' => 'page',
            'previousPage' => null,
            'nextPage' => 2,
            'currentPage' => 1,
            'hasPreviousPage' => false,
            'hasNextPage' => true,
        ], PaginatorMeta::from($users)->toArray());

        request()->merge(['page' => 2]);
        $users = User::query()->paginate(15);

        $this->assertEquals([
            'queryParam' => 'page',
            'previousPage' => 1,
            'nextPage' => 3,
            'currentPage' => 2,
            'hasPreviousPage' => true,
            'hasNextPage' => true,
        ], PaginatorMeta::from($users)->toArray());

        request()->merge(['page' => 3]);
        $users = User::query()->paginate(15);

        $this->assertEquals([
            'queryParam' => 'page',
            'previousPage' => 2,
            'nextPage' => null,
            'currentPage' => 3,
            'hasPreviousPage' => true,
            'hasNextPage' => false,
        ], PaginatorMeta::from($users)->toArray());
    }

    #[DataProvider('wrappedOrUnwrappedProvider')]
    public function test_extract_metadata_from_cursor_paginator(bool $wrappedinHttpResource)
    {
        $users = User::query()->cursorPaginate(15);

        if ($wrappedinHttpResource) {
            $users = UserResource::collection($users);
        }

        $this->assertEquals([
            'queryParam' => 'cursor',
            'previousPage' => null,
            'nextPage' => $users->nextCursor()?->encode(),
            'currentPage' => 1,
            'hasPreviousPage' => false,
            'hasNextPage' => true,
        ], $first = PaginatorMeta::from($users)->toArray());

        request()->merge(['cursor' => $first['nextPage']]);
        $users = User::query()->cursorPaginate(15);

        $this->assertEquals([
            'queryParam' => 'cursor',
            'previousPage' => $users->previousCursor()?->encode(),
            'nextPage' => $users->nextCursor()?->encode(),
            'currentPage' => $first['nextPage'],
            'hasPreviousPage' => true,
            'hasNextPage' => true,
        ], $second = PaginatorMeta::from($users)->toArray());

        request()->merge(['cursor' => $second['nextPage']]);
        $users = User::query()->cursorPaginate(15);

        $this->assertEquals([
            'queryParam' => 'cursor',
            'previousPage' => $users->previousCursor()?->encode(),
            'nextPage' => null,
            'currentPage' => $second['nextPage'],
            'hasPreviousPage' => true,
            'hasNextPage' => false,
        ], PaginatorMeta::from($users)->toArray());
    }

    public function test_throws_exception_if_not_a_paginator()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The given value is not a Laravel paginator instance. Use a custom callback to extract pagination metadata.');

        PaginatorMeta::from(collect());
    }
}
