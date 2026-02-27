<?php

namespace Inertia\Tests;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Inertia\SessionKey;
use Inertia\Tests\Stubs\ExampleMiddleware;

class RefreshPropsTest extends TestCase
{
    public function test_refresh_stores_keys_in_session(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            Inertia::refresh('foo');

            $this->assertSame(['foo'], session(SessionKey::Refresh->value));

            return response('ok');
        });

        $this->get('/')->assertSuccessful();
    }

    public function test_refresh_is_chainable(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            $result = Inertia::refresh('foo', 'bar');

            $this->assertInstanceOf(\Inertia\ResponseFactory::class, $result);

            return response('ok');
        });

        $this->get('/')->assertSuccessful();
    }

    public function test_refresh_accepts_an_array_of_keys(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            Inertia::refresh(['foo', 'bar']);

            $this->assertSame(['foo', 'bar'], session(SessionKey::Refresh->value));

            return response('ok');
        });

        $this->get('/')->assertSuccessful();
    }

    public function test_refresh_accepts_strings_keys(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            Inertia::refresh('foo', 'bar', 'baz');

            $this->assertSame(['foo', 'bar', 'baz'], session(SessionKey::Refresh->value));

            return response('ok');
        });

        $this->get('/')->assertSuccessful();
    }

    public function test_refresh_accumulates_multiple_calls(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            Inertia::refresh('foo');
            Inertia::refresh('bar');

            $this->assertSame(['foo', 'bar'], session(SessionKey::Refresh->value));

            return response('ok');
        });

        $this->get('/')->assertSuccessful();
    }

    public function test_refresh_deduplicates_keys(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            Inertia::refresh('foo', 'bar', 'foo');

            $this->assertSame(['foo', 'bar'], session(SessionKey::Refresh->value));

            return response('ok');
        });

        $this->get('/')->assertSuccessful();
    }

    public function test_once_prop_is_re_sent_when_key_is_in_refresh(): void
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Except-Once-Props' => 'foo']);

        $session = new \Illuminate\Session\Store('test', new \Illuminate\Session\NullSessionHandler);
        $session->put(SessionKey::Refresh->value, ['foo']);
        $request->setLaravelSession($session);

        $response = new Response('User/Edit', [
            'foo' => Inertia::once(fn () => 'bar'),
        ], 'app', '123');

        $response = $response->toResponse($request);
        /** @var JsonResponse $response */
        $page = $response->getData();

        $this->assertSame('bar', $page->props->foo);
    }

    public function test_once_prop_is_not_re_sent_when_key_is_not_in_refresh(): void
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Except-Once-Props' => 'foo']);

        $session = new \Illuminate\Session\Store('test', new \Illuminate\Session\NullSessionHandler);
        $session->put(SessionKey::Refresh->value, ['other']);
        $request->setLaravelSession($session);

        $response = new Response('User/Edit', [
            'foo' => Inertia::once(fn () => 'bar'),
        ], 'app', '123');

        $response = $response->toResponse($request);
        /** @var JsonResponse $response */
        $page = $response->getData();

        $this->assertFalse(isset($page->props->foo));
    }

    public function test_once_prop_is_re_sent_on_partial_request_when_key_is_in_refresh(): void
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Partial-Component' => 'User/Edit']);
        $request->headers->add(['X-Inertia-Partial-Data' => 'foo']);
        $request->headers->add(['X-Inertia-Except-Once-Props' => 'foo']);

        $session = new \Illuminate\Session\Store('test', new \Illuminate\Session\NullSessionHandler);
        $session->put(SessionKey::Refresh->value, ['foo']);
        $request->setLaravelSession($session);

        $response = new Response('User/Edit', [
            'foo' => Inertia::once(fn () => 'bar'),
        ], 'app', '123');

        $response = $response->toResponse($request);
        /** @var JsonResponse $response */
        $page = $response->getData();

        $this->assertSame('bar', $page->props->foo);
    }

    public function test_once_prop_is_not_sent_on_partial_request_when_refreshed_but_not_asked(): void
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Partial-Component' => 'User/Edit']);
        $request->headers->add(['X-Inertia-Partial-Data' => 'test']);
        $request->headers->add(['X-Inertia-Except-Once-Props' => 'foo']);

        $session = new \Illuminate\Session\Store('test', new \Illuminate\Session\NullSessionHandler);
        $session->put(SessionKey::Refresh->value, ['foo']);
        $request->setLaravelSession($session);

        $response = new Response('User/Edit', [
            'test' => 'value',
            'foo' => Inertia::once(fn () => 'bar'),
        ], 'app', '123');

        $response = $response->toResponse($request);
        /** @var JsonResponse $response */
        $page = $response->getData();

        $this->assertSame('value', $page->props->test);
        $this->assertFalse(isset($page->props->foo));
    }

    public function test_once_prop_with_custom_key_is_re_sent_when_key_is_in_refresh(): void
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Except-Once-Props' => 'my-foo']);

        $session = new \Illuminate\Session\Store('test', new \Illuminate\Session\NullSessionHandler);
        $session->put(SessionKey::Refresh->value, ['my-foo']);
        $request->setLaravelSession($session);

        $response = new Response('User/Edit', [
            'foo' => Inertia::once(fn () => 'bar')->as('my-foo'),
        ], 'app', '123');

        $response = $response->toResponse($request);
        /** @var JsonResponse $response */
        $page = $response->getData();

        $this->assertSame('bar', $page->props->foo);
    }

    public function test_defer_once_prop_is_re_queued_when_key_is_in_refresh(): void
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Except-Once-Props' => 'foo']);

        $session = new \Illuminate\Session\Store('test', new \Illuminate\Session\NullSessionHandler);
        $session->put(SessionKey::Refresh->value, ['foo']);
        $request->setLaravelSession($session);

        $response = new Response('User/Edit', [
            'foo' => Inertia::defer(fn () => 'bar')->once(),
        ], 'app', '123');

        $response = $response->toResponse($request);
        /** @var JsonResponse $response */
        $page = $response->getData();

        $this->assertSame(['default' => ['foo']], (array) $page->deferredProps);
    }

    public function test_defer_once_prop_is_not_re_queued_when_key_is_not_in_refresh(): void
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Except-Once-Props' => 'foo']);

        $session = new \Illuminate\Session\Store('test', new \Illuminate\Session\NullSessionHandler);
        $session->put(SessionKey::Refresh->value, ['other']);
        $request->setLaravelSession($session);

        $response = new Response('User/Edit', [
            'foo' => Inertia::defer(fn () => 'bar')->once(),
        ], 'app', '123');

        $response = $response->toResponse($request);
        /** @var JsonResponse $response */
        $page = $response->getData();

        $this->assertFalse(isset($page->deferredProps));
    }

    public function test_defer_once_prop_without_refresh_stays_excluded(): void
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Except-Once-Props' => 'foo']);

        $response = new Response('User/Edit', [
            'foo' => Inertia::defer(fn () => 'bar')->once(),
        ], 'app', '123');

        $response = $response->toResponse($request);
        /** @var JsonResponse $response */
        $page = $response->getData();

        $this->assertFalse(isset($page->deferredProps));
    }

    public function test_refresh_keys_are_reflashed_on_redirect(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->post('/logout', function () {
            Inertia::refresh('foo');

            return redirect('/login');
        });

        $response = $this->post('/logout', [], ['X-Inertia' => 'true']);

        $response->assertRedirect('/login');
        $response->assertSessionHas(SessionKey::Refresh->value, ['foo']);
    }

    public function test_get_refreshed_returns_session_keys(): void
    {
        $factory = new \Inertia\ResponseFactory;
        $request = Request::create('/');
        $session = new \Illuminate\Session\Store('test', new \Illuminate\Session\NullSessionHandler);
        $session->put(SessionKey::Refresh->value, ['foo', 'bar']);
        $request->setLaravelSession($session);

        $this->assertSame(['foo', 'bar'], $factory->getRefreshed($request));
    }

    public function test_get_refreshed_returns_empty_array_without_session(): void
    {
        $factory = new \Inertia\ResponseFactory;
        $request = Request::create('/');

        $this->assertSame([], $factory->getRefreshed($request));
    }

    public function test_complete_refresh_example(): void
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->post('/dashboard', function () {
            return Inertia::render('Dashboard', [
                'foo' => Inertia::once(fn () => 'bar'),
            ]);
        });

        Route::middleware([StartSession::class, ExampleMiddleware::class])->post('/logout', function () {
            Inertia::refresh('foo');

            return redirect('/login');
        });

        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/login', function () {
            return Inertia::render('Auth/Login', [
                'foo' => Inertia::once(fn () => 'baz'),
            ]);
        });

        $dashboardResponse = $this->post('/dashboard', [], ['X-Inertia' => 'true']);
        $dashboardResponse->assertSuccessful();
        $dashboardResponse->assertJson(['props' => ['foo' => 'bar']]);
        $dashboardResponse->assertSessionMissing(SessionKey::Refresh->value);

        $logoutResponse = $this->post('/logout', [], ['X-Inertia' => 'true']);
        $logoutResponse->assertRedirect('/login');
        $logoutResponse->assertSessionHas(SessionKey::Refresh->value, ['foo']);

        $loginResponse = $this->get('/login', [
            'X-Inertia' => 'true',
            'X-Inertia-Except-Once-Props' => 'foo',
        ]);

        $loginResponse->assertSuccessful();
        $loginResponse->assertJson(['props' => ['foo' => 'baz']]);
        $loginResponse->assertSessionMissing(SessionKey::Refresh->value);
    }
}
