<?php

namespace Inertia\Tests\Stubs;

use Illuminate\Http\Request;
use Illuminate\Routing\ResponseFactory;
use Inertia\Middleware;
use PHPUnit\Framework\Assert;

class CustomOncePropsResolverMiddleware extends Middleware
{
    public function oncePropsResolver()
    {
        return function ($request, ResponseFactory $otherDependency) {
            Assert::assertInstanceOf(Request::class, $request);
            Assert::assertInstanceOf(ResponseFactory::class, $otherDependency);

            return [
                'once' => true,
                'appName' => 'test',
            ];
        };
    }
}
