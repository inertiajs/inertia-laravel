<?php

namespace Inertia;

class Directive
{
    /**
     * Compiles the "@inertia" directive.
     */
    public static function compile(string $expression = ''): string
    {
        $id = mb_trim(mb_trim($expression), "\'\"") ?: 'app';

        $template = '<?php
            if (!isset($__inertiaSsrDispatched)) {
                $__inertiaSsrDispatched = true;
                $__inertiaSsrResponse = app(\Inertia\Ssr\Gateway::class)->dispatch($page);
            }

            if ($__inertiaSsrResponse) {
                echo $__inertiaSsrResponse->body;
            } else {
                ?><div id="'.$id.'" data-page="{{ json_encode($page) }}"></div><?php
            }
        ?>';

        return implode(' ', array_map('trim', explode("\n", $template)));
    }

    /**
     * Compiles the "@inertiaHead" directive.
     */
    public static function compileHead(string $expression = ''): string
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
