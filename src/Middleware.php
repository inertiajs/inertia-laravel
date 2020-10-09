<?php

namespace Inertia;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
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
    public function shared($request)
    {
        return [
            'errors' => function () {
                if (! Session::has('errors')) {
                    return (object) [];
                }

                return (object) Collection::make(Session::get('errors')->getBags())->map(function ($bag) {
                    return (object) Collection::make($bag->messages())->map(function ($errors) {
                        return $errors[0];
                    })->toArray();
                })->pipe(function ($bags) {
                    return $bags->has('default') ? $bags->get('default') : $bags->toArray();
                });
            },
        ];
    }

    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  callable  $next
     * @return \Illuminate\Http\Response
     */
    public function handle($request, $next)
    {
        Inertia::share($this->shared($request));

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
