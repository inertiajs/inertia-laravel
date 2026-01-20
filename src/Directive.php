<?php

namespace Inertia;

class Directive
{
    /**
     * Compile the "@inertia" Blade directive. This directive renders the
     * Inertia root element with the page data, handling both client-side
     * rendering and SSR fallback scenarios.
     *
     * @param  string  $expression
     */
    public static function compile($expression = ''): string
    {
        $id = trim(trim($expression), "\'\"") ?: 'app';

        $template = '<?php
            if (!isset($__inertiaSsrDispatched)) {
                $__inertiaSsrDispatched = true;
                $__inertiaSsrResponse = app(\Inertia\Ssr\Gateway::class)->dispatch($page);
            }

            if ($__inertiaSsrResponse) {
                echo $__inertiaSsrResponse->body;
            } elseif (config(\'inertia.use_script_element_for_initial_page\')) {
                ?><script data-page="'.$id.'" type="application/json">{!! json_encode($page) !!}</script><div id="'.$id.'"></div><?php
            } else {
                ?><div id="'.$id.'" data-page="{{ json_encode($page) }}"></div><?php
            }
        ?>';

        return implode(' ', array_map('trim', explode("\n", $template)));
    }

    /**
     * Compile the "@inertiaHead" Blade directive. This directive renders the
     * head content for SSR responses, including meta tags, title, and other
     * head elements from the server-side render.
     *
     * @param  string  $expression
     */
    public static function compileHead($expression = ''): string
    {
        $template = '<?php
            if (!isset($__inertiaSsrDispatched)) {
                $__inertiaSsrDispatched = true;
                $__inertiaSsrResponse = app(\Inertia\Ssr\Gateway::class)->dispatch($page);
            }

            if ($__inertiaSsrResponse) {
                echo $__inertiaSsrResponse->head;
            }
        ?>';

        return implode(' ', array_map('trim', explode("\n", $template)));
    }
}
