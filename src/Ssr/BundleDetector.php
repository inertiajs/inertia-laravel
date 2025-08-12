<?php

namespace Inertia\Ssr;

class BundleDetector
{
    /**
     * Detect and return the path to the SSR bundle file.
     *
     * @return string|null
     */
    public function detect()
    {
        return collect([
            config('inertia.ssr.bundle'),
            base_path('bootstrap/ssr/ssr.mjs'),
            base_path('bootstrap/ssr/ssr.js'),
            public_path('js/ssr.js'),
        ])->filter()->first(function ($path) {
            return file_exists($path);
        });
    }
}
