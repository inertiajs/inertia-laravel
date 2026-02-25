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
        $response->assertJsonMissing(['encryptHistory' => true]);
        $response->assertJsonMissing(['clearHistory' => true]);
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
        $response->assertJsonMissing(['encryptHistory' => true]);
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
        $response->assertContent('<script data-page="app" type="application/json">{"component":"User\/Edit","props":{"errors":{}},"url":"\/users","version":"","clearHistory":true}</script><div id="app"></div>');
    }

    public function test_the_fragment_is_not_preserved_by_default(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            return Inertia::render('User/Edit');
        });

        $response = $this->withoutExceptionHandling()->get('/', [
            'X-Inertia' => 'true',
        ]);

        $response->assertSuccessful();
        $response->assertJsonMissing([
            'preserveFragment' => true,
        ]);
    }

    public function test_the_fragment_can_be_preserved_via_inertia_facade(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            Inertia::preserveFragment();

            return redirect('/users');
        });

        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/users', function () {
            return Inertia::render('User/Edit');
        });

        $this->withoutExceptionHandling()->get('/');

        $response = $this->withoutExceptionHandling()->get('/users', [
            'X-Inertia' => 'true',
        ]);

        $response->assertSuccessful();
        $response->assertJson([
            'preserveFragment' => true,
        ]);
    }

    public function test_the_fragment_can_be_preserved_via_redirect_macro(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            return redirect('/users')->preserveFragment(); /** @phpstan-ignore method.notFound */
        });

        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/users', function () {
            return Inertia::render('User/Edit');
        });

        $this->withoutExceptionHandling()->get('/');

        $response = $this->withoutExceptionHandling()->get('/users', [
            'X-Inertia' => 'true',
        ]);

        $response->assertSuccessful();
        $response->assertJson([
            'preserveFragment' => true,
        ]);
    }
}
