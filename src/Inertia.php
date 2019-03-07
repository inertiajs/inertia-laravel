<?php

namespace Inertia;

use Illuminate\Support\Facades\Facade;

class Inertia extends Facade
{
    protected static function getFacadeAccessor()
    {
        return Component::class;
    }
}
