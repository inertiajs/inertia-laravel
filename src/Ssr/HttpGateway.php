<?php

namespace Inertia\Ssr;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class HttpGateway implements Gateway
{
    /**
     * Dispatch the Inertia page to the Server Side Rendering engine.
     *
     * @param  array  $page
     * @return Response|null
     */
    public function dispatch(array $page): ?Response
    {
        $endpoint = Config::get('inertia.ssr_url', 'http://127.0.0.1:8080/render');

        try {
            [$head, $body] = Http::post($endpoint, $page)->throw()->json();
        } catch (RequestException $e) {
            return null;
        }

        return new Response(
            implode("\n", $head),
            $body
        );
    }
}
