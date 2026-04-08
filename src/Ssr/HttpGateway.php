<?php

namespace Inertia\Ssr;

use Closure;
use Exception;
use Illuminate\Foundation\Http\Middleware\Concerns\ExcludesPaths;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\ResolvesCallables;

class HttpGateway implements DisablesSsr, ExcludesSsrPaths, Gateway, HasHealthCheck
{
    use ExcludesPaths;
    use ResolvesCallables;

    /**
     * The paths that should be excluded from server-side rendering.
     *
     * @var array<int, string>
     */
    protected $except = [];

    /**
     * The condition that determines if SSR is disabled.
     */
    protected Closure|bool|null $disabled = null;

    /**
     * Dispatch the Inertia page to the SSR engine via HTTP.
     *
     * @param  array<string, mixed>  $page
     */
    public function dispatch(array $page, ?Request $request = null): ?Response
    {
        if (! $this->ssrIsEnabled($request ?? request())) {
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

            if ($response->failed()) {
                $this->handleSsrFailure($page, $response->json());

                return null;
            }

            if (! $data = $response->json()) {
                return null;
            }

            return new Response(
                implode("\n", $data['head'] ?? []),
                $data['body'] ?? ''
            );
        } catch (Exception $e) {
            if ($e instanceof StrayRequestException || $e instanceof SsrException) {
                throw $e;
            }

            $this->handleSsrFailure($page, [
                'error' => $e->getMessage(),
                'type' => 'connection',
            ]);

            return null;
        }
    }

    /**
     * Set the condition that determines if SSR should be disabled.
     */
    public function disable(Closure|bool $condition): void
    {
        $this->disabled = $condition;
    }

    /**
     * Exclude the given paths from server-side rendering.
     *
     * @param  array<int, string>|string  $paths
     */
    public function except(array|string $paths): void
    {
        $this->except = array_merge($this->except, Arr::wrap($paths));
    }

    /**
     * Handle an SSR rendering failure.
     *
     * @param  array<string, mixed>  $page
     * @param  array<string, mixed>|null  $error
     *
     * @throws SsrException
     */
    protected function handleSsrFailure(array $page, ?array $error): void
    {
        $event = new SsrRenderFailed(
            page: $page,
            error: $error['error'] ?? 'Unknown SSR error',
            type: SsrErrorType::fromString($error['type'] ?? null),
            hint: $error['hint'] ?? null,
            browserApi: $error['browserApi'] ?? null,
            stack: $error['stack'] ?? null,
            sourceLocation: $error['sourceLocation'] ?? null,
        );

        // Dispatch the event so users can listen for SSR failures
        SsrRenderFailed::dispatch(
            $event->page,
            $event->error,
            $event->type,
            $event->hint,
            $event->browserApi,
            $event->stack,
            $event->sourceLocation,
        );

        // Throw an exception if configured (useful for E2E testing)
        if (config('inertia.ssr.throw_on_error', false)) {
            throw SsrException::fromEvent($event);
        }
    }

    /**
     * Determine if the SSR feature is enabled.
     */
    protected function ssrIsEnabled(Request $request): bool
    {
        $enabled = $this->disabled !== null
            ? ! $this->resolveCallable($this->disabled)
            : config('inertia.ssr.enabled', true);

        return $enabled && ! $this->inExceptArray($request);
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
