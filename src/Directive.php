<?php

namespace Inertia;

class Directive
{
    public static function compile($expression = ''): string
    {
        return '<div id="app" data-page="{{ json_encode($page) }}"></div>';
    }
}
