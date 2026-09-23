<?php

namespace Inertia\Middleware;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Inertia\Support\Header;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeferredCallbacksRun
{
    /**
     * Inertia returns a 409 for responses the client has to navigate itself, but
     * those requests did succeed. Mark the pending callbacks as always so
     * Laravel does not skip them for a response it reads as failed.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! $this->isControlResponse($response)) {
            return $response;
        }

        $callbacks = Container::getInstance()->make(DeferredCallbackCollection::class);

        for ($index = 0, $count = count($callbacks); $index < $count; $index++) {
            $callbacks[$index]->always();
        }

        return $response;
    }

    /**
     * Determine if the response instructs the client to navigate on its own.
     */
    protected function isControlResponse(Response $response): bool
    {
        if ($response->getStatusCode() !== 409) {
            return false;
        }

        if ($response->headers->has(Header::REDIRECT)) {
            return true;
        }

        // A location visit caused by an asset version mismatch carries the new version
        // and makes the client replay the request, so the callbacks registered by
        // that replay run instead. Anything else navigates away for good.
        return $response->headers->has(Header::LOCATION)
            && ! $response->headers->has(Header::VERSION);
    }
}
