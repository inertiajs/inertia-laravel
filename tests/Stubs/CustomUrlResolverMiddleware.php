<?php

namespace Inertia\Tests\Stubs;

use Illuminate\Http\Request;
use Illuminate\Routing\ResponseFactory;
use Inertia\Middleware;
use PHPUnit\Framework\Assert;

class CustomUrlResolverMiddleware extends Middleware
{
    public function urlResolver()
    {
        return function ($request, ResponseFactory $otherDependency) {
            Assert::assertInstanceOf(Request::class, $request);
            Assert::assertInstanceOf(ResponseFactory::class, $otherDependency);

            return '/my-custom-url';
        };
    }
}
