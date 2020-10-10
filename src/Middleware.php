<?php

namespace Inertia;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Middleware
{
    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    public function version(Request $request)
    {
        // When we are running on Laravel Vapor, asset URLs are automatically updated
        // when the application is being deployed. Because of this, we do not need
        // to use any files for hashing, as we can simply use this URL instead.
        if (config('app.asset_url')) {
            return md5(config('app.asset_url'));
        }

        // Alternatively, when we are running in a regular (non-serverless) environment
        // we'll attempt to use the Laravel Mix asset manifest to generate our hash.
        if (file_exists($manifest = public_path('mix-manifest.json'))) {
            return md5_file($manifest);
        }
    }

    /**
     * Defines the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function share(Request $request)
    {
        return [
            'errors' => function () use ($request) {
                return $this->resolveValidationErrors($request);
            },
        ];
    }

    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  Closure  $next
     * @return Response
     */
    public function handle(Request $request, Closure $next)
    {
        Inertia::version(function () use ($request) {
            return $this->version($request);
        });

        Inertia::share($this->share($request));

        $response = $next($request);
        $response = $this->checkVersion($request, $response);
        $response = $this->changeRedirectCode($request, $response);

        return $response;
    }

    /**
     * In the event that the asset version changes, Inertia will automatically reload
     * the page in order to ensure the application keeps working for the end user.
     *
     * @param  Request  $request
     * @param  Response  $response
     * @return Response
     */
    public function checkVersion(Request $request, Response $response)
    {
        if ($request->header('X-Inertia') &&
            $request->method() === 'GET' &&
            $request->header('X-Inertia-Version', '') !== Inertia::getVersion()
        ) {
            if ($request->hasSession()) {
                $request->session()->reflash();
            }

            return Inertia::location($request->fullUrl());
        }

        return $response;
    }

    /**
     * Changes the status code during redirects, ensuring they are made as
     * GET requests, preventing "MethodNotAllowedHttpException" errors.
     *
     * @param  Request  $request
     * @param  Response  $response
     * @return Response
     */
    public function changeRedirectCode(Request $request, Response $response)
    {
        if ($request->header('X-Inertia') &&
            $response->getStatusCode() === 302 &&
            in_array($request->method(), ['PUT', 'PATCH', 'DELETE'])
        ) {
            $response->setStatusCode(303);
        }

        return $response;
    }

    /**
     * Resolves and prepares validation errors in such a way
     * that they are easier to use in the Inertia client.
     *
     * @param  Request  $request
     * @return object
     */
    public function resolveValidationErrors(Request $request)
    {
        if (! $request->session()->has('errors')) {
            return (object) [];
        }

        return (object) collect($request->session()->get('errors')->getBags())->map(function ($bag) {
            return (object) collect($bag->messages())->map(function ($errors) {
                return $errors[0];
            })->toArray();
        })->pipe(function ($bags) {
            return $bags->has('default') ? $bags->get('default') : $bags->toArray();
        });
    }
}
