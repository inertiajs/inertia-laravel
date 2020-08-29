<?php

namespace Inertia\Tests;

use Illuminate\Foundation\Testing\TestResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class MiddlewareTest extends TestCase
{
    public function test_the_version_is_optional()
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);

        $response = $this->makeMockResponse($request);

        $response->assertSuccessful();
        $response->assertJson(['component' => 'User/Edit']);
    }

    public function test_the_version_can_be_a_number()
    {
        Inertia::version(1597347897973);

        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Version' => '1597347897973']);

        $response = $this->makeMockResponse($request);

        $response->assertSuccessful();
        $response->assertJson(['component' => 'User/Edit']);
    }

    public function test_the_version_can_be_a_string()
    {
        Inertia::version('foo-version');

        $request = Request::create('/user/edit', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Version' => 'foo-version']);

        $response = $this->makeMockResponse($request);

        $response->assertSuccessful();
        $response->assertJson(['component' => 'User/Edit']);
    }

    public function test_the_version_can_be_a_closure()
    {
        Inertia::version(function () {
            return md5('Inertia');
        });

        $request = Request::create('/user/edit', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Version' => 'b19a24ee5c287f42ee1d465dab77ab37']);

        $response = $this->makeMockResponse($request);

        $response->assertSuccessful();
        $response->assertJson(['component' => 'User/Edit']);
    }

    public function test_it_will_instruct_inertia_to_reload_on_a_version_mismatch()
    {
        Inertia::version(1234);

        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Version' => 4321]);

        $response = $this->makeMockResponse($request);

        $response->assertStatus(409);
        $response->assertHeader('X-Inertia-Location', $request->fullUrl());
        self::assertEmpty($response->content());
    }

    private function makeMockResponse($request)
    {
        $response = (new Middleware())->handle($request, function ($request) {
            return Inertia::render('User/Edit', ['user' => ['name' => 'Jonathan']])->toResponse($request);
        });

        return TestResponse::fromBaseResponse($response);
    }
}
