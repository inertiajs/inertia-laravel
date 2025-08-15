<?php

namespace Inertia\Tests;

use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Tests\Stubs\CustomInitialPropsResolverMiddleware;
use Inertia\Tests\Stubs\ExampleMiddleware;

class InitialPropTest extends TestCase
{
    public function test_initial_props_accessibility()
    {
        $this->prepareMockEndpoint();

        $response = $this->withoutExceptionHandling()->get('/');

        $response->assertSuccessful();
        $this->assertSame(
            '<div id="app" data-page="{&quot;component&quot;:&quot;User\/Edit&quot;,&quot;props&quot;:{&quot;errors&quot;:{}},&quot;url&quot;:&quot;\/&quot;,&quot;version&quot;:&quot;&quot;,&quot;clearHistory&quot;:false,&quot;encryptHistory&quot;:false,&quot;initialProps&quot;:{&quot;initial&quot;:true,&quot;appName&quot;:&quot;test&quot;}}"></div>',
            $response->content(),
        );
    }

    public function test_initial_props_are_not_accessible()
    {
        $this->prepareMockEndpoint();

        $response = $this->withoutExceptionHandling()->get('/', [
            'X-Inertia' => 'true',
        ]);

        $response->assertSuccessful();
        $response->assertJsonMissingPath('initialProps');
    }

    private function prepareMockEndpoint(): \Illuminate\Routing\Route
    {
        return Route::middleware([StartSession::class, ExampleMiddleware::class, CustomInitialPropsResolverMiddleware::class])->get('/', function () {
            return Inertia::render('User/Edit');
        });
    }
}
