<?php

namespace Inertia;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Contracts\View\View render($component, $props = [])
 * @method static array share($key, $value)
 * @method static void setRootView($name)
 *
 * @see \Inertia\Component
 */
class Inertia extends Facade
{
    protected static function getFacadeAccessor()
    {
        return Component::class;
    }
}
