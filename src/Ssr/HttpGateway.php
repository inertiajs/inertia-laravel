<?php

namespace Inertia\Ssr;

use Exception;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Http;

class HttpGateway implements Gateway
{
    /**
     * Dispatch the Inertia page to the Server Side Rendering engine.
     */
    public function dispatch(array $page): ?Response
    {
        if (! config('inertia.ssr.enabled', true)) {
            return null;
        }

        if (! $this->shouldDispatchWithoutBundle() && ! $this->bundleExists()) {
            return null;
        }

        if (! $url = $this->getHttpUrl()) {
            return null;
        }

        try {
            $response = Http::post($url, $page)->throw()->json();
        } catch (Exception $e) {
            if ($e instanceof StrayRequestException) {
                throw $e;
            }

            return null;
        }

        if (is_null($response)) {
            return null;
        }

        return new Response(
            implode("\n", $response['head']),
            $response['body']
        );
    }

    /**
     * Determine if dispatch should proceed even if no bundle is detected.
     */
    protected function shouldDispatchWithoutBundle(): bool
    {
        return config('inertia.ssr.dispatch_without_bundle', false);
    }

    /**
     * Check if an SSR bundle exists.
     */
    protected function bundleExists(): bool
    {
        return (new BundleDetector)->detect() !== null;
    }

    /**
     * Get the SSR URL from the configuration, ensuring it ends with '/render'.
     */
    public function getHttpUrl(): ?string
    {
        return str_replace('/render', '', rtrim(config('inertia.ssr.url', 'http://127.0.0.1:13714'), '/')).'/render';
    }
}
