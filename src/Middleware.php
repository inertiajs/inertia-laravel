<?php

namespace Inertia;

use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\RedirectResponse as Redirect;

abstract class Middleware
{
    /**
     * Determine the current Inertia asset version hash
     * used for automatic asset cache busting.
     *
     * @see https://inertiajs.com/asset-versioning
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    abstract public function version($request);

    /**
     * Defines the Inertia properties that automatically
     * shared as of default. Can be overwritten.
     *
     * @see https://inertiajs.com/shared-data
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    abstract public function share($request);

    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  callable  $next
     * @return \Illuminate\Http\Response
     */
    public function handle($request, $next)
    {
        Inertia::share($this->share($request));

        Inertia::version(function () use ($request) {
            return $this->version($request);
        });

        $response = $next($request);

        return $this->handleAfter($request, $response);
    }

    /**
     * Handles the resolved request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param $response
     * @return \Illuminate\Http\Response|mixed|Redirect
     */
    protected function handleAfter($request, $response)
    {
        if (! $request->header('X-Inertia')) {
            return $response;
        }

        if ($request->method() === 'GET' && $request->header('X-Inertia-Version', '') !== Inertia::getVersion()) {
            if ($request->hasSession()) {
                $request->session()->reflash();
            }

            return Response::make('', 409, ['X-Inertia-Location' => $request->fullUrl()]);
        }

        if ($response instanceof Redirect
            && $response->getStatusCode() === 302
            && in_array($request->method(), ['PUT', 'PATCH', 'DELETE'])
        ) {
            $response->setStatusCode(303);
        }

        return $response;
    }
}
