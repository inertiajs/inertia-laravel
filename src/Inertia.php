<?php

namespace Inertia;

use Illuminate\Support\Facades\Facade;
use Illuminate\Contracts\Support\Arrayable;

/**
 * @method static void setRootView(string $name)
 * @method static void share($key, $value = null)
 * @method static array getShared(string $key = null, $default = null)
 * @method static array flushShared()
 * @method static void version($version)
 * @method static int|string getVersion()
 * @method static LazyProp lazy(callable $callback)
 * @method static Response render($component, array|Arrayable $props = [])
 * @method static \Symfony\Component\HttpFoundation\Response location(string $url)
 *
 * @see \Inertia\ResponseFactory
 */
class Inertia extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ResponseFactory::class;
    }
}
