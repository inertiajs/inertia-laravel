<?php

declare(strict_types=1);

namespace Inertia\Tests;

use Illuminate\Http\Request;
use Inertia\ClosureUrlResolver;

class ClosureUrlResolverTest extends TestCase
{
    public function test_it_resolves_the_url_from_the_given_closure(): void
    {
        $request = Request::create('/');

        $urlResolver = new ClosureUrlResolver(fn () => '/my/path');

        $this->assertSame('/my/path', $urlResolver->resolve($request));
    }
}
