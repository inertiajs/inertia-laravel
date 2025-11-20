<?php

namespace Inertia;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\MessageBag;
use Inertia\Support\Header;
use Symfony\Component\HttpFoundation\Response;

class Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines if validation errors should be mapped to a single error message per field.
     *
     * @var bool
     */
    protected $withAllErrors = false;

    /**
     * Determine the current asset version.
     *
     * @return string|null
     */
    public function version(Request $request)
    {
        if (config('app.asset_url')) {
            return hash('xxh128', config('app.asset_url'));
        }

        if (file_exists($manifest = public_path('build/manifest.json'))) {
            return hash_file('xxh128', $manifest);
        }

        if (file_exists($manifest = public_path('mix-manifest.json'))) {
            return hash_file('xxh128', $manifest);
        }

        return null;
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request)
    {
        return [
            'errors' => Inertia::always($this->resolveValidationErrors($request)),
        ];
    }

    /**
     * Set the root template that is loaded on the first page visit.
     *
     * @return string
     */
    public function rootView(Request $request)
    {
        return $this->rootView;
    }

    /**
     * Define a callback that returns the relative URL.
     *
     * @return \Closure|null
     */
    public function urlResolver()
    {
        return null;
    }

    /**
     * Handle the incoming request.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next)
    {
        Inertia::version(function () use ($request) {
            return $this->version($request);
        });

        Inertia::share($this->share($request));
        Inertia::setRootView($this->rootView($request));

        if ($urlResolver = $this->urlResolver()) {
            Inertia::resolveUrlUsing($urlResolver);
        }

        $response = $next($request);
        $response->headers->set('Vary', Header::INERTIA);

        if (! $request->header(Header::INERTIA)) {
            return $response;
        }

        if ($request->method() === 'GET' && $request->header(Header::VERSION, '') !== Inertia::getVersion()) {
            $response = $this->onVersionChange($request, $response);
        }

        if ($response->isOk() && empty($response->getContent())) {
            $response = $this->onEmptyResponse($request, $response);
        }

        if ($response->getStatusCode() === 302 && in_array($request->method(), ['PUT', 'PATCH', 'DELETE'])) {
            $response->setStatusCode(303);
        }

        return $response;
    }

    /**
     * Handle empty responses.
     */
    public function onEmptyResponse(Request $request, Response $response): Response
    {
        return Redirect::back();
    }

    /**
     * Handle version changes.
     */
    public function onVersionChange(Request $request, Response $response): Response
    {
        if ($request->hasSession()) {
            /** @var Store $session */
            $session = $request->session();
            $session->reflash();
        }

        return Inertia::location($request->fullUrl());
    }

    /**
     * Resolve validation errors for client-side use.
     *
     * @return object
     */
    public function resolveValidationErrors(Request $request)
    {
        if (! $request->hasSession() || ! $request->session()->has('errors')) {
            return (object) [];
        }

        /** @var array<string, MessageBag> $bags */
        $bags = $request->session()->get('errors')->getBags();

        return (object) collect($bags)->map(function ($bag) {
            return (object) collect($bag->messages())->map(function ($errors) {
                return $this->withAllErrors ? $errors : $errors[0];
            })->toArray();
        })->pipe(function ($bags) use ($request) {
            if ($bags->has('default') && $request->header(Header::ERROR_BAG)) {
                return [$request->header(Header::ERROR_BAG) => $bags->get('default')];
            }

            if ($bags->has('default')) {
                return $bags->get('default');
            }

            return $bags->toArray();
        });
    }
}
