<?php

namespace Inertia\Tests\Stubs;

use Illuminate\Http\Request;
use Illuminate\Routing\ResponseFactory;
use Inertia\Middleware;
use PHPUnit\Framework\Assert;

class CustomInitialPropsResolverMiddleware extends Middleware
{
    public function initialPropsResolver(): callable
    {
        return function ($request, ResponseFactory $otherDependency) {
            Assert::assertInstanceOf(Request::class, $request);
            Assert::assertInstanceOf(ResponseFactory::class, $otherDependency);

            return [
                'initial' => true,
                'appName' => 'test',
            ];
        };
    }
}
