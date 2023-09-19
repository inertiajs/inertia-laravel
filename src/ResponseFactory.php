<?php

namespace Inertia;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Response as BaseResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

class ResponseFactory
{
    use Macroable;

    /** @var string */
    protected $rootView = 'app';

    /** @var array */
    protected $sharedProps = [];

    /** @var Closure|string|null */
    protected $version;

    public function setRootView(string $name): void
    {
        $this->rootView = $name;
    }

    /**
     * @param string|array|Arrayable $key
     * @param mixed                  $value
     */
    public function share($key, $value = null): void
    {
        if (is_array($key)) {
            $this->sharedProps = array_merge($this->sharedProps, $key);
        } elseif ($key instanceof Arrayable) {
            $this->sharedProps = array_merge($this->sharedProps, $key->toArray());
        } else {
            Arr::set($this->sharedProps, $key, $value);
        }
    }

    /**
     * @param mixed $default
     *
     * @return mixed
     */
    public function getShared(string $key = null, $default = null)
    {
        if ($key) {
            return Arr::get($this->sharedProps, $key, $default);
        }

        return $this->sharedProps;
    }

    /**
     * Resolve the prop value.
     *
     * @param  mixed  $prop
     * @return mixed
     */
    public function resolveProp($prop)
    {
        return value($prop instanceof LazyProp ? Closure::fromCallable($prop) : $prop);
    }

    /**
     * Resolve the shared prop value.
     *
     * @return mixed
     */
    public function resolveShared(string $key = null, mixed $default = null)
    {
        return $this->resolveProp($this->getShared($key, $default));
    }

    /**
     * Merge existing props with the given array.
     *
     * @param  array|Closure|LazyProp  $props
     * @param  array|Arrayable  $new
     * @return array|Closure|LazyProp
     */
    public function mergeProps($props, $new)
    {
        $new = $new instanceof Arrayable ? $new->toArray() : $new;

        if ($props instanceof LazyProp) {
            return new LazyProp(function () use ($props, $new) {
                return array_merge($props(), $new);
            });
        }

        if ($props instanceof Closure) {
            return function () use ($props, $new) {
                return array_merge($props(), $new);
            };
        }

        return array_merge($props, $new);
    }

    /**
     * Retrieved the shared props and merge with the given array.
     *
     * @param  array|Arrayale  $new
     * @return array|Closure|LazyProp
     */
    public function getSharedAndMergeProps(string $key, $new)
    {
        $new = $new instanceof Arrayable ? $new->toArray() : $new;

        $shared = $this->getShared($key);

        if ($shared === null) {
            return $new;
        }

        return $this->mergeProps($shared, $new);
    }

    public function flushShared(): void
    {
        $this->sharedProps = [];
    }

    /**
     * @param Closure|string|null $version
     */
    public function version($version): void
    {
        $this->version = $version;
    }

    public function getVersion(): string
    {
        $version = $this->version instanceof Closure
            ? App::call($this->version)
            : $this->version;

        return (string) $version;
    }

    public function lazy(callable $callback): LazyProp
    {
        return new LazyProp($callback);
    }

    /**
     * @param array|Arrayable $props
     */
    public function render(string $component, $props = []): Response
    {
        if ($props instanceof Arrayable) {
            $props = $props->toArray();
        }

        return new Response(
            $component,
            array_merge($this->sharedProps, $props),
            $this->rootView,
            $this->getVersion()
        );
    }

    /**
     * @param string|SymfonyRedirect $url
     */
    public function location($url): SymfonyResponse
    {
        if (Request::inertia()) {
            return BaseResponse::make('', 409, ['X-Inertia-Location' => $url instanceof SymfonyRedirect ? $url->getTargetUrl() : $url]);
        }

        return $url instanceof SymfonyRedirect ? $url : Redirect::away($url);
    }
}
