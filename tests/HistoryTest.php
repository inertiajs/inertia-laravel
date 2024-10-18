<?php

namespace Inertia\Tests;

use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Inertia\EncryptHistoryMiddleware;
use Inertia\Inertia;
use Inertia\Tests\Stubs\ExampleMiddleware;

class HistoryTest extends TestCase
{
    public function test_the_history_is_not_encrypted_or_cleared_by_default(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            return Inertia::render('User/Edit');
        });

        $response = $this->withoutExceptionHandling()->get('/', [
            'X-Inertia' => 'true',
        ]);

        $response->assertSuccessful();
        $response->assertJson([
            'component' => 'User/Edit',
            'encryptHistory' => false,
            'clearHistory' => false,
        ]);
    }

    public function test_the_history_can_be_encrypted(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            Inertia::encryptHistory();

            return Inertia::render('User/Edit');
        });

        $response = $this->withoutExceptionHandling()->get('/', [
            'X-Inertia' => 'true',
        ]);

        $response->assertSuccessful();
        $response->assertJson([
            'component' => 'User/Edit',
            'encryptHistory' => true,
        ]);
    }

    public function test_the_history_can_be_encrypted_via_middleware(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class, EncryptHistoryMiddleware::class])->get('/', function () {
            return Inertia::render('User/Edit');
        });

        $response = $this->withoutExceptionHandling()->get('/', [
            'X-Inertia' => 'true',
        ]);

        $response->assertSuccessful();
        $response->assertJson([
            'component' => 'User/Edit',
            'encryptHistory' => true,
        ]);
    }

    public function test_the_history_can_be_encrypted_via_middleware_alias(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class, 'inertia.encrypt'])->get('/', function () {
            return Inertia::render('User/Edit');
        });

        $response = $this->withoutExceptionHandling()->get('/', [
            'X-Inertia' => 'true',
        ]);

        $response->assertSuccessful();
        $response->assertJson([
            'component' => 'User/Edit',
            'encryptHistory' => true,
        ]);
    }

    public function test_the_history_can_be_encrypted_globally(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            Config::set('inertia.history.encrypt', true);

            return Inertia::render('User/Edit');
        });

        $response = $this->withoutExceptionHandling()->get('/', [
            'X-Inertia' => 'true',
        ]);

        $response->assertSuccessful();
        $response->assertJson([
            'component' => 'User/Edit',
            'encryptHistory' => true,
        ]);
    }

    public function test_the_history_can_be_encrypted_globally_and_overridden(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            Config::set('inertia.history.encrypt', true);

            Inertia::encryptHistory(false);

            return Inertia::render('User/Edit');
        });

        $response = $this->withoutExceptionHandling()->get('/', [
            'X-Inertia' => 'true',
        ]);

        $response->assertSuccessful();
        $response->assertJson([
            'component' => 'User/Edit',
            'encryptHistory' => false,
        ]);
    }

    public function test_the_history_can_be_cleared(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            Inertia::clearHistory();

            return Inertia::render('User/Edit');
        });

        $response = $this->withoutExceptionHandling()->get('/', [
            'X-Inertia' => 'true',
        ]);

        $response->assertSuccessful();
        $response->assertJson([
            'component' => 'User/Edit',
            'clearHistory' => true,
        ]);
    }

    public function test_the_history_can_be_cleared_when_redirecting(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            Inertia::clearHistory();

            return redirect('/users');
        });

        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/users', function () {
            return Inertia::render('User/Edit');
        });

        $this->followingRedirects();

        $response = $this->withoutExceptionHandling()->get('/', [
            'X-Inertia' => 'true',
        ]);

        $response->assertSuccessful();
        $response->assertContent('<div id="app" data-page="{&quot;component&quot;:&quot;User\/Edit&quot;,&quot;props&quot;:{&quot;errors&quot;:{}},&quot;url&quot;:&quot;\/users&quot;,&quot;version&quot;:&quot;&quot;,&quot;clearHistory&quot;:true,&quot;encryptHistory&quot;:false}"></div>');
    }
}
