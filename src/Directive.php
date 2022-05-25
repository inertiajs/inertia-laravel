<?php

namespace Inertia;

use Illuminate\Support\Facades\Config;

class Directive
{
    /**
     * Compiles the "@inertia" directive.
     *
     * @param  string  $expression
     * @return string
     */
    public static function compile($expression = ''): string
    {
        $id = trim(trim($expression), "\'\"") ?: Config::get('inertia.app.root', 'app');
        $classes = Config::get('inertia.app.classes', '');
        $template = '<?php
            if (!isset($__inertiaSsr)) {
                $__inertiaSsr = app(\Inertia\Ssr\Gateway::class)->dispatch($page);
            }

            if ($__inertiaSsr instanceof \Inertia\Ssr\Response) {
                echo $__inertiaSsr->body;
            } else {
                ?><div id="'.$id.'" '.(! empty($classes) ? 'class="'.$classes.'" ' : '').'data-page="{{ json_encode($page) }}"></div><?php
            }
        ?>';

        return implode(' ', array_map('trim', explode("\n", $template)));
    }

    /**
     * Compiles the "@inertiaHead" directive.
     *
     * @param  string  $expression
     * @return string
     */
    public static function compileHead($expression = ''): string
    {
        $template = '<?php
            if (!isset($__inertiaSsr)) {
                $__inertiaSsr = app(\Inertia\Ssr\Gateway::class)->dispatch($page);
            }

            if ($__inertiaSsr instanceof \Inertia\Ssr\Response) {
                echo $__inertiaSsr->head;
            }
        ?>';

        return implode(' ', array_map('trim', explode("\n", $template)));
    }
}
