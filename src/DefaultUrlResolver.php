<?php

declare(strict_types=1);

namespace Inertia;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;

final class DefaultUrlResolver implements UrlResolver
{
    public function resolve(Request $request): string
    {
        return Str::of($request->url())
            ->after($request->getSchemeAndHttpHost())
            ->start('/')
            ->when($request->getQueryString(), fn(Stringable $url, string $queryString) => $url
                ->append('?')
                ->append(urldecode($queryString)))
            ->toString();
    }
}
