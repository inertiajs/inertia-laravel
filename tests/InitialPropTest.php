<?php

namespace Inertia\Tests;

use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\InitialProp;
use Inertia\Tests\Stubs\ExampleMiddleware;

class InitialPropTest extends TestCase
{
    public function test_initial_props_accessibility()
    {
        Inertia::share([
            'initial' => Inertia::initial(fn () => true),
            'appName' => Inertia::initial(fn () => 'test'),
        ]);

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
        Inertia::share([
            'initial' => Inertia::initial(fn () => true),
            'appName' => Inertia::initial(fn () => 'test'),
        ]);

        $this->prepareMockEndpoint();

        $response = $this->withoutExceptionHandling()->get('/', [
            'X-Inertia' => 'true',
        ]);

        $response->assertSuccessful();
        $response->assertJsonMissingPath('initialProps');
    }

    public function test_can_invoke(): void
    {
        $initialProp = new InitialProp(function () {
            return 'A initial value';
        });

        $this->assertSame('A initial value', $initialProp());
    }

    public function test_can_resolve_bindings_when_invoked(): void
    {
        $initialProp = new InitialProp(function (Request $request) {
            return $request;
        });

        $this->assertInstanceOf(Request::class, $initialProp());
    }

    private function prepareMockEndpoint(): \Illuminate\Routing\Route
    {
        return Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            return Inertia::render('User/Edit');
        });
    }
}
