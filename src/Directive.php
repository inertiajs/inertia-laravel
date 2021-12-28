<?php

namespace Inertia;

class Directive
{
    public static function compile($expression = ''): string
    {
        $id = trim(trim($expression), "\'\"") ?: 'app';

        return '<?php if (isset($inertiaSsr) && $inertiaSsr instanceof \Inertia\Ssr\Response) { '
            .'echo $inertiaSsr->body; '
            .'} else { ?>'
            .'<div id="'.$id.'" data-page="{{ json_encode($page) }}"></div>'
            .'<?php } ?>';
    }

    public static function compileHead($expression = ''): string
    {
        return '<?php $inertiaSsr = app(\Inertia\Ssr\Gateway::class)->dispatch($page); '
            .'if ($inertiaSsr instanceof \Inertia\Ssr\Response) { '
            .'  foreach($inertiaSsr->head as $element) { '
            .'    echo $element . "\n"; '
            .'  } '
            .'} '
            .'?>';
    }
}
