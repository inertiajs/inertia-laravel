<?php

declare(strict_types=1);

namespace Inertia;

use Illuminate\Http\Request;

interface UrlResolver
{
    public function resolve(Request $request): string;
}
