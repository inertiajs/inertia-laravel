<?php

namespace Inertia;

use Illuminate\Support\Facades\App;

class Translations
{

    public static function json()
    {
        $activeLangFile = lang_path(app()->getLocale() . '.json');

        if (!file_exists($activeLangFile)) {
            return [];
        }

        return json_decode(file_get_contents($activeLangFile), true);
    }
}
