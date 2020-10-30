<?php

namespace Inertia;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void setRootView($name)
 * @method static void share($key, $value = null)
 * @method static array getShared($key = null)
 * @method static void version($version)
 * @method static int|string getVersion()
 * @method static \Inertia\Response render($component, $props = [])
 * @method static \Illuminate\Http\Response location($url)
 * @method static \Inertia\LazyProp lazy(callable $callback)
 *
 * @see \Inertia\ResponseFactory
 */
class Inertia extends Facade
{
    protected static function getFacadeAccessor()
    {
        return ResponseFactory::class;
    }
}
