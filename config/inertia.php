<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Force top-level property interaction
    |--------------------------------------------------------------------------
    |
    | This setting allows you to toggle the automatic property interaction
    | check for your page's top-level properties. While this feature is
    | useful in property scopes, it is generally not as useful on the
    | top-level of the page, as it forces you to interact with each
    | (shared) property on the page, or to use the `etc` method.
    |
    */

    'force_top_level_property_interaction' => false,

    /*
    |--------------------------------------------------------------------------
    | Page
    |--------------------------------------------------------------------------
    |
    | The values described here are used to locate Inertia components on the
    | filesystem. For instance, when using `assertInertia`, the assertion
    | attempts to locate the component as a file relative to any of the
    | paths AND with any of the extensions specified here.
    |
    */

    'page' => [

        /**
         * Determines whether assertions should check that Inertia page components
         * actually exist on the filesystem instead of just checking responses.
         */
        'should_exist' => true,

        /*
         * A list of root paths to your Inertia page components.
         */
        'paths' => [

            resource_path('js/Pages'),

        ],

        /*
         * A list of valid Inertia page component extensions.
         */
        'extensions' => [

            'vue',
            'svelte',

        ],

    ],

];
