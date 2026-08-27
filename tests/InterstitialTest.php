<?php

namespace Inertia\Tests;

use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Tests\Stubs\ExampleMiddleware;
use Inertia\Tests\Stubs\InterstitialMiddleware;

class InterstitialTest extends TestCase
{
    public function test_a_normal_response_has_no_interstitial_flag(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            return Inertia::render('User/Edit');
        });

        $response = $this->withoutExceptionHandling()->get('/', [
            'X-Inertia' => 'true',
        ]);

        $response->assertSuccessful();
        $response->assertJsonMissing([
            'interstitial' => true,
        ]);
    }

    public function test_the_interstitial_flag_can_be_set_via_the_inertia_facade(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            Inertia::interstitial();

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
            'interstitial' => true,
        ]);
    }

    public function test_the_interstitial_flag_can_be_set_via_the_redirect_macro(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            return redirect('/users')->interstitial(); /** @phpstan-ignore method.notFound */
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
            'interstitial' => true,
        ]);
    }

    public function test_the_interstitial_flag_can_be_set_from_middleware(): void
    {
        Route::middleware([StartSession::class, InterstitialMiddleware::class])->get('/redirect', function () {
            return 'never reached';
        });

        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/users', function () {
            return Inertia::render('User/Edit');
        });

        $this->withoutExceptionHandling()->get('/redirect');

        $response = $this->withoutExceptionHandling()->get('/users', [
            'X-Inertia' => 'true',
        ]);

        $response->assertSuccessful();
        $response->assertJson([
            'interstitial' => true,
        ]);
    }

    public function test_a_close_response_never_carries_the_interstitial_flag(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/redirect', function () {
            Inertia::interstitial();

            return redirect('/close');
        });

        Route::middleware([StartSession::class, ExampleMiddleware::class])->post('/close', function () {
            return Inertia::close();
        });

        $this->withoutExceptionHandling()->get('/redirect');

        $response = $this->withoutExceptionHandling()->post('/close', [], [
            'X-Inertia' => 'true',
        ]);

        $response->assertSuccessful();
        $response->assertExactJson([
            'component' => '',
            'props' => [],
            'url' => '/close',
            'version' => '',
            'close' => true,
        ]);
    }

    public function test_a_close_response_clears_the_interstitial_flag_from_the_session(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/redirect', function () {
            Inertia::interstitial();

            return redirect('/close');
        });

        Route::middleware([StartSession::class, ExampleMiddleware::class])->post('/close', function () {
            return Inertia::close();
        });

        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/users', function () {
            return Inertia::render('User/Edit');
        });

        $this->withoutExceptionHandling()->get('/redirect');

        $this->withoutExceptionHandling()->post('/close', [], [
            'X-Inertia' => 'true',
        ]);

        // The close abandoned the pending capture, so a later, unrelated page must not inherit it.
        $response = $this->withoutExceptionHandling()->get('/users', [
            'X-Inertia' => 'true',
        ]);

        $response->assertSuccessful();
        $response->assertJsonMissing(['interstitial' => true]);
    }
}
