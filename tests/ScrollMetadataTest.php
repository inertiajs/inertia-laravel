<?php

namespace Inertia\Tests;

use Inertia\ScrollMetadata;
use Inertia\Tests\Stubs\User;
use Inertia\Tests\Stubs\UserResource;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;

class ScrollMetadataTest extends TestCase
{
    use InteractsWithUserModels;

    /**
     * @return array<string, array<bool>>
     */
    public static function wrappedOrUnwrappedProvider(): array
    {
        return [
            'wrapped in http resource' => [true],
            'not wrapped in http resource' => [false],
        ];
    }

    #[DataProvider('wrappedOrUnwrappedProvider')]
    public function test_extract_metadata_from_simple_paginator(bool $wrappedinHttpResource): void
    {
        $users = User::query()->simplePaginate(15);

        if ($wrappedinHttpResource) {
            $users = UserResource::collection($users);
        }

        $this->assertEquals([
            'pageName' => 'page',
            'previousPage' => null,
            'nextPage' => 2,
            'currentPage' => 1,
        ], ScrollMetadata::fromPaginator($users)->toArray());

        request()->merge(['page' => 2]);
        $users = User::query()->simplePaginate(15);

        $this->assertEquals([
            'pageName' => 'page',
            'previousPage' => 1,
            'nextPage' => 3,
            'currentPage' => 2,
        ], ScrollMetadata::fromPaginator($users)->toArray());

        request()->merge(['page' => 3]);
        $users = User::query()->simplePaginate(15);

        $this->assertEquals([
            'pageName' => 'page',
            'previousPage' => 2,
            'nextPage' => null,
            'currentPage' => 3,
        ], ScrollMetadata::fromPaginator($users)->toArray());
    }

    #[DataProvider('wrappedOrUnwrappedProvider')]
    public function test_extract_metadata_from_length_aware_paginator(bool $wrappedinHttpResource): void
    {
        $users = User::query()->paginate(15);

        if ($wrappedinHttpResource) {
            $users = UserResource::collection($users);
        }

        $this->assertEquals([
            'pageName' => 'page',
            'previousPage' => null,
            'nextPage' => 2,
            'currentPage' => 1,
        ], ScrollMetadata::fromPaginator($users)->toArray());

        request()->merge(['page' => 2]);
        $users = User::query()->paginate(15);

        $this->assertEquals([
            'pageName' => 'page',
            'previousPage' => 1,
            'nextPage' => 3,
            'currentPage' => 2,
        ], ScrollMetadata::fromPaginator($users)->toArray());

        request()->merge(['page' => 3]);
        $users = User::query()->paginate(15);

        $this->assertEquals([
            'pageName' => 'page',
            'previousPage' => 2,
            'nextPage' => null,
            'currentPage' => 3,
        ], ScrollMetadata::fromPaginator($users)->toArray());
    }

    #[DataProvider('wrappedOrUnwrappedProvider')]
    public function test_extract_metadata_from_cursor_paginator(bool $wrappedinHttpResource): void
    {
        $users = User::query()->cursorPaginate(15);

        if ($wrappedinHttpResource) {
            $users = UserResource::collection($users);
        }

        $this->assertEquals([
            'pageName' => 'cursor',
            'previousPage' => null,
            'nextPage' => $users->nextCursor()?->encode(),
            'currentPage' => 1,
        ], $first = ScrollMetadata::fromPaginator($users)->toArray());

        request()->merge(['cursor' => $first['nextPage']]);
        $users = User::query()->cursorPaginate(15);

        $this->assertEquals([
            'pageName' => 'cursor',
            'previousPage' => $users->previousCursor()?->encode(),
            'nextPage' => $users->nextCursor()?->encode(),
            'currentPage' => $first['nextPage'],
        ], $second = ScrollMetadata::fromPaginator($users)->toArray());

        request()->merge(['cursor' => $second['nextPage']]);
        $users = User::query()->cursorPaginate(15);

        $this->assertEquals([
            'pageName' => 'cursor',
            'previousPage' => $users->previousCursor()?->encode(),
            'nextPage' => null,
            'currentPage' => $second['nextPage'],
        ], ScrollMetadata::fromPaginator($users)->toArray());
    }

    public function test_throws_exception_if_not_a_paginator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The given value is not a Laravel paginator instance. Use a custom callback to extract pagination metadata.');

        ScrollMetadata::fromPaginator(collect());
    }
}
