<?php

namespace Inertia;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response as BaseResponse;
use Illuminate\Support\Traits\Macroable;
use Inertia\Support\Header;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ResponseFactory
{
    use Macroable;

    /** @var string */
    protected $rootView = 'app';

    /** @var array */
    protected $sharedProps = [];

    /** @var Closure|string|null */
    protected $version;

    protected $clearHistory = false;

    protected $encryptHistory;

    /***
     * @param string $name The name of the root view
     * @return void
     */
    public function setRootView(string $name): void
    {
        $this->rootView = $name;
    }


    /**
     * @param  string|array|Arrayable  $key
     * @param  mixed  $value
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
     * @param string|null $key
     * @param  mixed  $default
     * @return mixed
     */
    public function getShared(?string $key = null, $default = null)
    {
        if ($key) {
            return Arr::get($this->sharedProps, $key, $default);
        }

        return $this->sharedProps;
    }


    /**
     * @return void
     */
    public function flushShared()
    {
        $this->sharedProps = [];
    }

    /**
     * @param  Closure|string|null  $version
     * @return void
     */
    public function version($version): void
    {
        $this->version = $version;
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        $version = $this->version instanceof Closure
            ? App::call($this->version)
            : $this->version;

        return (string) $version;
    }

    /**
     * @return void
     */
    public function clearHistory(): void
    {
        session(['inertia.clear_history' => true]);
    }

    /**
     * @param bool $encrypt
     * @return void
     */
    public function encryptHistory($encrypt = true): void
    {
        $this->encryptHistory = $encrypt;
    }

    /**
     * @deprecated Use `optional` instead.
     */
    public function lazy(callable $callback): LazyProp
    {
        return new LazyProp($callback);
    }

    /**
     * @param callable $callback
     * @return OptionalProp
     */
    public function optional(callable $callback): OptionalProp
    {
        return new OptionalProp($callback);
    }

    /**
     *
     * @param callable $callback
     * @param string $group
     * @return DeferProp
     */
    public function defer(callable $callback, string $group = 'default'): DeferProp
    {
        return new DeferProp($callback, $group);
    }

    /**
     * @param  mixed  $value
     * @return MergeProp
     */
    public function merge($value): MergeProp
    {
        return new MergeProp($value);
    }

    /**
     * @param mixed $value
     * @return MergeProp
     */
    public function deepMerge($value): MergeProp
    {
        return (new MergeProp($value))->deepMerge();
    }

    /**
     * @param  mixed  $value
     * @return AlwaysProp
     */
    public function always($value): AlwaysProp
    {
        return new AlwaysProp($value);
    }

    /**
     * @param string $component
     * @param array|Arrayable $props
     * @return Response
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
            $this->getVersion(),
            $this->encryptHistory ?? config('inertia.history.encrypt', false),
        );
    }

    /**
     * @param  string|SymfonyRedirect  $url
     * @return SymfonyResponse
     */
    public function location($url): SymfonyResponse
    {
        if (Request::inertia()) {
            return BaseResponse::make('', 409, [Header::LOCATION => $url instanceof SymfonyRedirect ? $url->getTargetUrl() : $url]);
        }

        return $url instanceof SymfonyRedirect ? $url : Redirect::away($url);
    }
}
