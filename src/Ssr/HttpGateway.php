<?php

namespace Inertia\Ssr;

use Exception;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class HttpGateway implements Gateway, HasHealthCheck
{
    /**
     * Dispatch the Inertia page to the SSR engine via HTTP.
     *
     * @param  array<string, mixed>  $page
     */
    public function dispatch(array $page): ?Response
    {
        if (! $this->shouldDispatch()) {
            return null;
        }

        try {
            $response = Http::post($this->getUrl('/render'), $page)->throw()->json();
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
     * Determine if the page should be dispatched to the SSR engine.
     */
    protected function shouldDispatch(): bool
    {
        return $this->ssrIsEnabled() && ($this->shouldDispatchWithoutBundle() || $this->bundleExists());
    }

    /**
     * Determine if the SSR feature is enabled.
     */
    protected function ssrIsEnabled(): bool
    {
        return config('inertia.ssr.enabled', true);
    }

    /**
     * Determine if the SSR server is healthy.
     */
    public function isHealthy(): bool
    {
        try {
            return Http::get($this->getUrl('/health'))->successful();
        } catch (Exception $e) {
            if ($e instanceof StrayRequestException) {
                throw $e;
            }

            return false;
        }
    }

    /**
     * Determine if dispatch should proceed without bundle detection.
     */
    protected function shouldDispatchWithoutBundle(): bool
    {
        return ! config('inertia.ssr.ensure_bundle_exists', true);
    }

    /**
     * Check if an SSR bundle exists.
     */
    protected function bundleExists(): bool
    {
        return (new BundleDetector)->detect() !== null;
    }

    /**
     * Get the complete SSR URL by combining the base URL with the given path.
     */
    public function getUrl(string $path): string
    {
        $path = Str::start($path, '/');

        return str_replace($path, '', rtrim(config('inertia.ssr.url', 'http://127.0.0.1:13714'), '/')).$path;
    }
}
