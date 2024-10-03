<?php

namespace Inertia;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void setRootView(string $name)
 * @method static void share(string|array|\Illuminate\Contracts\Support\Arrayable $key, mixed $value = null)
 * @method static mixed getShared(string|null $key = null, mixed $default = null)
 * @method static void clearHistory()
 * @method static void encryptHistory($encrypt = true)
 * @method static void flushShared()
 * @method static void version(\Closure|string|null $version)
 * @method static string getVersion()
 * @method static \Inertia\OptionalProp optional(callable $callback)
 * @method static \Inertia\LazyProp lazy(callable $callback)
 * @method static \Inertia\DeferProp defer(callable $callback, string $group = 'default')
 * @method static \Inertia\AlwaysProp always(mixed $value)
 * @method static \Inertia\MergeProp merge(mixed $value)
 * @method static \Inertia\Response render(string $component, array|\Illuminate\Contracts\Support\Arrayable $props = [])
 * @method static \Symfony\Component\HttpFoundation\Response location(string|\Symfony\Component\HttpFoundation\RedirectResponse $url)
 * @method static void macro(string $name, object|callable $macro)
 * @method static void mixin(object $mixin, bool $replace = true)
 * @method static bool hasMacro(string $name)
 * @method static void flushMacros()
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
