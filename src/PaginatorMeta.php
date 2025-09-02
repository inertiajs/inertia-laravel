<?php

namespace Inertia;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use InvalidArgumentException;

/**
 * Represents pagination meta information for Inertia responses.
 *
 * This class extracts and normalizes pagination metadata from Laravel
 * paginators for consistent client-side pagination handling.
 *
 * @implements Arrayable<string, mixed>
 */
class PaginatorMeta implements Arrayable
{
    /**
     * Create a new paginator meta instance.
     */
    public function __construct(
        public string $queryParam,
        public int|string|null $previousPage = null,
        public int|string|null $nextPage = null,
        public int|string|null $currentPage = null,
        public bool $hasPreviousPage = false,
        public bool $hasNextPage = false,
    ) {
        //
    }

    /**
     * Convert the paginator meta to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return [
            'queryParam' => $this->queryParam,
            'previousPage' => $this->previousPage,
            'nextPage' => $this->nextPage,
            'currentPage' => $this->currentPage,
            'hasPreviousPage' => $this->hasPreviousPage,
            'hasNextPage' => $this->hasNextPage,
        ];
    }

    /**
     * Create a paginator meta instance from a Laravel paginator.
     */
    public static function from(mixed $value, ?Request $request = null): self
    {
        $paginator = $value instanceof JsonResource ? $value->resource : $value;

        $request ??= request();

        if ($paginator instanceof CursorPaginator) {
            return new self(
                $cursorName = $paginator->getCursorName(),
                $paginator->previousCursor()?->encode(),
                $paginator->nextCursor()?->encode(),
                $paginator->onFirstPage() ? 1 : $request->query($cursorName, 1),
                ! $paginator->onFirstPage(),
                $paginator->hasMorePages(),
            );
        }

        if ($paginator instanceof LengthAwarePaginator || $paginator instanceof Paginator) {
            return new self(
                $paginator->getPageName(),
                $paginator->currentPage() > 1 ? $paginator->currentPage() - 1 : null,
                $paginator->hasMorePages() ? $paginator->currentPage() + 1 : null,
                $paginator->currentPage(),
                $paginator->currentPage() > 1,
                $paginator->hasMorePages(),
            );
        }

        throw new InvalidArgumentException('The given value is not a Laravel paginator instance. Use a custom callback to transform the value instead.');
    }
}
