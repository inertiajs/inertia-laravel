<?php

namespace Inertia\Ssr;

use Exception;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Vite;

class HttpGateway implements Gateway
{
    /**
     * Dispatch the Inertia page to the Server Side Rendering engine.
     */
    public function dispatch(array $page): ?Response
    {
        if (! config('inertia.ssr.enabled', true) || ! ($url = $this->getHttpUrl())) {
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
     * Use the Vite asset URL if Vite is running in hot mode, otherwise
     * return the SSR URL from the configuration if the bundle is detected.
     */
    public function getHttpUrl(): ?string
    {
        if (Vite::isRunningHot()) {
            return Vite::asset('render');
        } elseif ((new BundleDetector)->detect()) {
            return $this->getSsrUrl();
        }

        return null;
    }

    /**
     * Get the SSR URL from the configuration, ensuring it ends with '/render'.
     */
    public function getSsrUrl(): ?string
    {
        return str_replace('/render', '', rtrim(config('inertia.ssr.url', 'http://127.0.0.1:13714'), '/')).'/render';
    }
}
