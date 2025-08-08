<?php

use Illuminate\Http\Request;
use Illuminate\Routing\Route as RouteInstance;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Inertia\Response;
use Inertia\ResponseFactory;

// TestResponse macros for Inertia
TestResponse::macro('assertInertia', function (?Closure $callback = null) {
    /** @var TestResponse $this */
    return $this;
});

TestResponse::macro('inertiaPage', function (): array {
    /** @var TestResponse $this */
    return [];
});

TestResponse::macro('inertiaProps', function (?string $propName = null): mixed {
    /** @var TestResponse $this */
    return null;
});

// Request macro for Inertia
Request::macro('inertia', function (): bool {
    /** @var Request $this */
    return false;
});

// Route facade macro for Inertia
Route::macro('inertia', function (string $uri, string $component, array $props = []): RouteInstance {
    return app('router')->get($uri, function () {});
});

// Test macros for Response and ResponseFactory
Response::macro('foo', function (): string {
    /** @var Response $this */
    return 'bar';
});

ResponseFactory::macro('foo', function (): string {
    /** @var ResponseFactory $this */
    return 'bar';
});
