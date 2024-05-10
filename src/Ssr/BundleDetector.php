<?php

namespace Inertia\Ssr;

class BundleDetector
{
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
