<?php

namespace Inertia\Ssr;

use Exception;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Vite;
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
        if (! $this->ssrIsEnabled()) {
            return null;
        }

        $isHot = Vite::isRunningHot();

        if (! $isHot && $this->shouldEnsureBundleExists() && ! $this->bundleExists()) {
            return null;
        }

        $url = $isHot
            ? $this->getHotUrl('/__inertia_ssr')
            : $this->getProductionUrl('/render');

        try {
            $response = Http::post($url, $page);

            if ($response->failed() || ! $data = $response->json()) {
                return null;
            }

            return new Response(
                implode("\n", $data['head'] ?? []),
                $data['body'] ?? ''
            );
        } catch (Exception $e) {
            if ($e instanceof StrayRequestException) {
                throw $e;
            }

            return null;
        }
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
            return Http::get($this->getProductionUrl('/health'))->successful();
        } catch (Exception $e) {
            if ($e instanceof StrayRequestException) {
                throw $e;
            }

            return false;
        }
    }

    /**
     * Determine if the bundle existence should be ensured.
     */
    protected function shouldEnsureBundleExists(): bool
    {
        return config('inertia.ssr.ensure_bundle_exists', true);
    }

    /**
     * Check if an SSR bundle exists.
     */
    protected function bundleExists(): bool
    {
        return app(BundleDetector::class)->detect() !== null;
    }

    /**
     * Get the production SSR server URL.
     */
    public function getProductionUrl(string $path = '/'): string
    {
        $path = Str::start($path, '/');
        $baseUrl = rtrim(config('inertia.ssr.url', 'http://127.0.0.1:13714'), '/');

        return $baseUrl.$path;
    }

    /**
     * Get the Vite hot SSR URL.
     */
    protected function getHotUrl(string $path = '/'): string
    {
        return rtrim(file_get_contents(Vite::hotFile())).$path;
    }
}
