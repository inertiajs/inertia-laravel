<?php

namespace Inertia\Support;

use Laravel\Vapor\Vapor;

class LaravelVapor
{
    public static function detect(): bool
    {
        if (class_exists(Vapor::class)) {
            return Vapor::active();
        }

        return false;
    }
}
