<?php

namespace Inertia;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void setRootView(string $name)
 * @method static void share(string|array<array-key, mixed>|\Illuminate\Contracts\Support\Arrayable<array-key, mixed>|\Inertia\ProvidesInertiaProperties $key, mixed $value = null)
 * @method static mixed getShared(string|null $key = null, mixed $default = null)
 * @method static void clearHistory()
 * @method static void encryptHistory($encrypt = true)
 * @method static void flushShared()
 * @method static void version(\Closure|string|null $version)
 * @method static string getVersion()
 * @method static void resolveUrlUsing(\Closure|null $urlResolver = null)
 * @method static \Inertia\ScrollProp<mixed> scroll(mixed $value, string $wrapper = 'data', \Inertia\ProvidesScrollMetadata|callable|null $metadata = null)
 * @method static \Inertia\OptionalProp optional(callable $callback)
 * @method static \Inertia\LazyProp lazy(callable $callback)
 * @method static \Inertia\DeferProp defer(callable $callback, string $group = 'default')
 * @method static \Inertia\OnceProp once(callable $callback)
 * @method static \Inertia\AlwaysProp always(mixed $value)
 * @method static \Inertia\MergeProp merge(mixed $value)
 * @method static \Inertia\MergeProp deepMerge(mixed $value)
 * @method static \Inertia\Response render(string $component, array<array-key, mixed>|\Illuminate\Contracts\Support\Arrayable<array-key, mixed>|\Inertia\ProvidesInertiaProperties $props = [])
 * @method static \Illuminate\Http\RedirectResponse back(int $status = 302, array<string, string> $headers = [], mixed $fallback = false)
 * @method static \Symfony\Component\HttpFoundation\Response location(string|\Symfony\Component\HttpFoundation\RedirectResponse $url)
 * @method static \Inertia\ResponseFactory flash(string|array<string, mixed> $key, mixed $value = null)
 * @method static array<string, mixed> getFlashed(?\Illuminate\Http\Request $request = null)
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
