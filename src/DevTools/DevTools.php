<?php

namespace Inertia\DevTools;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class DevTools
{
    /**
     * Route default key holding the source location where a Route::inertia() route
     * was defined, used as the render source for route-defined renders.
     */
    public const RENDER_SOURCE_KEY = '__inertiaDevtoolsRenderSource';

    public static function enabled(): bool
    {
        $configured = config('inertia.devtools.enabled');

        if ($configured === null) {
            return app()->environment('local');
        }

        return (bool) $configured;
    }

    /**
     * The recorder to report the request lifecycle to, or null when nothing should be
     * recorded. Pass the request wherever one is in hand so excluded paths skip the work.
     */
    public static function recorder(?Request $request = null): ?RequestRecorder
    {
        return static::enabledForRequest($request) ? app(RequestRecorder::class) : null;
    }

    /**
     * Whether the given request should be recorded, defaulting to the current one so callers
     * without a request in scope do not have to resolve it themselves.
     */
    public static function enabledForRequest(?Request $request = null): bool
    {
        if (! static::enabled()) {
            return false;
        }

        // Read without the typed config helper: a misconfigured value would throw, and this
        // runs inside the app's own request, where recording must never be the thing that
        // breaks the response.
        $patterns = array_values(array_filter(Arr::wrap(config('inertia.devtools.except', [])), 'is_string'));

        return $patterns === [] || ! ($request ?? request())->is(...$patterns);
    }
}
