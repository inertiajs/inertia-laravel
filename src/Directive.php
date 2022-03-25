<?php

namespace Inertia;

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
        $args = explode(',', $expression);
        $id = !empty($args[0]) ?  trim(trim($args[0]), "\'\"") : 'app';
        $classes = isset($args[1]) ? trim(trim($args[1]), "\'\"") : '';

        $template = '<?php
            if (!isset($__inertiaSsr)) {
                $__inertiaSsr = app(\Inertia\Ssr\Gateway::class)->dispatch($page);
            }

            if ($__inertiaSsr instanceof \Inertia\Ssr\Response) {
                echo $__inertiaSsr->body;
            } else {
                ?><div id="'.$id.'" ' . (!empty($classes) ? 'class="' . $classes . '"' : '') . 'data-page="{{ json_encode($page) }}"></div><?php
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
