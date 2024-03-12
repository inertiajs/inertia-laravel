<?php

declare(strict_types=1);

namespace Inertia;

use Illuminate\Http\Request;

final class ClosureUrlResolver implements UrlResolver
{
    public function __construct(
        private readonly \Closure $resolve,
    )
    {
    }

    public function resolve(Request $request): string
    {
        return ($this->resolve)($request);
    }
}
