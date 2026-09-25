<?php

namespace Inertia\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Inertia\Support\Header;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeferredCallbacksRun
{
    /**
     * Mark the pending deferred callbacks as always when the client performs the
     * redirect itself, since Laravel skips them on the 409 response even
     * though the request succeeded.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! $this->isClientRedirect($response)) {
            return $response;
        }

        $callbacks = app(DeferredCallbackCollection::class);

        for ($index = 0, $count = count($callbacks); $index < $count; $index++) {
            $callbacks[$index]->always();
        }

        return $response;
    }

    /**
     * Determine if the response instructs the client to perform the redirect itself.
     */
    protected function isClientRedirect(Response $response): bool
    {
        if ($response->getStatusCode() !== 409) {
            return false;
        }

        if ($response->headers->has(Header::REDIRECT)) {
            return true;
        }

        // A location visit caused by an asset version mismatch carries the new version
        // and makes the client replay the request, so the callbacks registered by
        // that replay run instead.
        return $response->headers->has(Header::LOCATION)
            && ! $response->headers->has(Header::VERSION);
    }
}
