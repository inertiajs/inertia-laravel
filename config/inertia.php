<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Server Side Rendering
    |--------------------------------------------------------------------------
    |
    | These options configures if and how Inertia uses Server Side Rendering
    | to pre-render the initial visits made to your application's pages.
    |
    | You can specify a custom SSR bundle path, or omit it to let Inertia
    | try and automatically detect it for you.
    |
    | Do note that enabling these options will NOT automatically make SSR work,
    | as a separate rendering service needs to be available. To learn more,
    | please visit https://inertiajs.com/server-side-rendering
    |
    */

    'ssr' => [

        'enabled' => (bool) env('INERTIA_SSR_ENABLED', true),

        'url' => env('INERTIA_SSR_URL', 'http://127.0.0.1:13714'),

        'ensure_bundle_exists' => (bool) env('INERTIA_SSR_ENSURE_BUNDLE_EXISTS', true),

        // 'bundle' => base_path('bootstrap/ssr/ssr.mjs'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | If you want to ensure that the pages exist, you can set `ensure_pages_exist` to true.
    | This will throw an exception if the component does not exist on the filesystem
    | when rendering a page. You may configure this separately for testing.
    |
    */

    'ensure_pages_exist' => false,

    'page_paths' => [

        resource_path('js/Pages'),

    ],

    'page_extensions' => [

        'js',
        'jsx',
        'svelte',
        'ts',
        'tsx',
        'vue',

    ],

    /*
    |--------------------------------------------------------------------------
    | Testing
    |--------------------------------------------------------------------------
    |
    | The values described here are used to locate Inertia components on the
    | filesystem. For instance, when using `assertInertia`, the assertion
    | attempts to locate the component as a file relative to any of the
    | paths AND with any of the extensions specified here.
    |
    | By default, it uses the `page_paths` and `page_extensions` settings
    | defined above. You may override these values for testing purposes
    | by adding these two keys to this `testing` array.
    |
    */

    'testing' => [

        'ensure_pages_exist' => true,

    ],

    'history' => [

        'encrypt' => (bool) env('INERTIA_ENCRYPT_HISTORY', false),

    ],

];
