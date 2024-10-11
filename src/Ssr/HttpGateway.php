<?php

namespace Inertia\Ssr;

use Exception;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Http;

class HttpGateway implements Gateway
{
    public function __construct(
        private Vite $vite,
    ) {
    }

    /**
     * Dispatch the Inertia page to the Server Side Rendering engine.
     */
    public function dispatch(array $page): ?Response
    {
        if ($this->vite->isRunningHot()) {
            $url = file_get_contents($this->vite->hotFile()).'/render';
        } elseif (config('inertia.ssr.enabled', true) || (new BundleDetector)->detect()) {
            $url = str_replace('/render', '', config('inertia.ssr.url', 'http://127.0.0.1:13714')).'/render';
        } else {
            return null;
        }


        try {
            $response = Http::post($url, $page)->throw()->json();
        } catch (Exception $e) {
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
}
