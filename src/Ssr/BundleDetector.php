<?php

namespace Inertia\Ssr;

class BundleDetector
{
    /**
     * Detect the SSR bundle file location.
     */
    public function detect()
    {
        return collect([
            config('inertia.ssr.bundle'),
            base_path('bootstrap/ssr/ssr.mjs'),
            base_path('bootstrap/ssr/ssr.js'),
            public_path('js/ssr.js'),
        ])->filter()->first(fn ($path) => file_exists($path));
    }
}
