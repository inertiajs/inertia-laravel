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
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ResponseFactory
{
    use Macroable;

    /**
     * The name of the root view.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * The shared properties.
     *
     * @var array<string, mixed>
     */
    protected $sharedProps = [];

    /**
     * The asset version.
     *
     * @var Closure|string|null
     */
    protected $version;

    /**
     * Indicates if the browser history should be cleared.
     *
     * @var bool
     */
    protected $clearHistory = false;

    /**
     * Indicates if the browser history should be encrypted.
     *
     * @var bool|null
     */
    protected $encryptHistory;

    /**
     * The URL resolver callback.
     *
     * @var Closure|null
     */
    protected $urlResolver;

    /**
     * Set the root view template for Inertia responses. This template
     * serves as the HTML wrapper that contains the Inertia root element
     * where the frontend application will be mounted.
     */
    public function setRootView(string $name): void
    {
        $this->rootView = $name;
    }

    /**
     * Share data across all Inertia responses. This data is automatically
     * included with every response, making it ideal for user authentication
     * state, flash messages, etc.
     *
     * @param  string|array<array-key, mixed>|\Illuminate\Contracts\Support\Arrayable<array-key, mixed>|\Inertia\ProvidesInertiaProperties  $key
     * @param  mixed  $value
     */
    public function share($key, $value = null): void
    {
        if (is_array($key)) {
            $this->sharedProps = array_merge($this->sharedProps, $key);
        } elseif ($key instanceof Arrayable) {
            $this->sharedProps = array_merge($this->sharedProps, $key->toArray());
        } elseif ($key instanceof ProvidesInertiaProperties) {
            $this->sharedProps = array_merge($this->sharedProps, [$key]);
        } else {
            Arr::set($this->sharedProps, $key, $value);
        }
    }

    /**
     * Get the shared data for a given key. Returns all shared data if
     * no key is provided, or the value for a specific key with an
     * optional default fallback.
     *
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
     * Flush all shared data.
     *
     * @return void
     */
    public function flushShared()
    {
        $this->sharedProps = [];
    }

    /**
     * Set the asset version.
     *
     * @param  \Closure|string|null  $version
     */
    public function version($version): void
    {
        $this->version = $version;
    }

    /**
     * Get the asset version.
     */
    public function getVersion(): string
    {
        $version = $this->version instanceof Closure
            ? App::call($this->version)
            : $this->version;

        return (string) $version;
    }

    /**
     * Set the URL resolver.
     */
    public function resolveUrlUsing(?Closure $urlResolver = null): void
    {
        $this->urlResolver = $urlResolver;
    }

    /**
     * Clear the browser history on the next visit.
     */
    public function clearHistory(): void
    {
        session(['inertia.clear_history' => true]);
    }

    /**
     * Encrypt the browser history.
     *
     * @param  bool  $encrypt
     */
    public function encryptHistory($encrypt = true): void
    {
        $this->encryptHistory = $encrypt;
    }

    /**
     * Create a lazy property.
     *
     * @deprecated Use `optional` instead.
     */
    public function lazy(callable $callback): LazyProp
    {
        return new LazyProp($callback);
    }

    /**
     * Create an optional property.
     */
    public function optional(callable $callback): OptionalProp
    {
        return new OptionalProp($callback);
    }

    /**
     * Create a deferred property.
     */
    public function defer(callable $callback, string $group = 'default'): DeferProp
    {
        return new DeferProp($callback, $group);
    }

    /**
     * Create a merge property.
     *
     * @param  mixed  $value
     */
    public function merge($value): MergeProp
    {
        return new MergeProp($value);
    }

    /**
     * Create a deep merge property.
     *
     * @param  mixed  $value
     */
    public function deepMerge($value): MergeProp
    {
        return (new MergeProp($value))->deepMerge();
    }

    /**
     * Create an always property.
     *
     * @param  mixed  $value
     */
    public function always($value): AlwaysProp
    {
        return new AlwaysProp($value);
    }

    /**
     * Create an scroll property.
     *
     * @param  mixed  $value
     *
     * @template T
     *
     * @param  T  $value
     * @return ScrollProp<T>
     */
    public function scroll($value, string $wrapper = 'data', ProvidesScrollMetadata|callable|null $metadata = null): ScrollProp
    {
        return new ScrollProp($value, $wrapper, $metadata);
    }

    /**
     * Find the component or fail.
     *
     * @throws \Inertia\ComponentNotFoundException
     */
    protected function findComponentOrFail(string $component): void
    {
        try {
            app('inertia.view-finder')->find($component);
        } catch (InvalidArgumentException) {
            throw new ComponentNotFoundException("Inertia page component [{$component}] not found.");
        }
    }

    /**
     * Create an Inertia response.
     *
     * @param  array<array-key, mixed>|\Illuminate\Contracts\Support\Arrayable<array-key, mixed>|ProvidesInertiaProperties  $props
     */
    public function render(string $component, $props = []): Response
    {
        if (config('inertia.ensure_pages_exist', false)) {
            $this->findComponentOrFail($component);
        }

        if ($props instanceof Arrayable) {
            $props = $props->toArray();
        } elseif ($props instanceof ProvidesInertiaProperties) {
            // Will be resolved in Response::resolveResponsableProperties()
            $props = [$props];
        }

        return new Response(
            $component,
            array_merge($this->sharedProps, $props),
            $this->rootView,
            $this->getVersion(),
            $this->encryptHistory ?? config('inertia.history.encrypt', false),
            $this->urlResolver,
        );
    }

    /**
     * Create an Inertia location response.
     *
     * @param  string|\Symfony\Component\HttpFoundation\RedirectResponse  $url
     */
    public function location($url): SymfonyResponse
    {
        if (Request::inertia()) {
            return BaseResponse::make('', 409, [Header::LOCATION => $url instanceof SymfonyRedirect ? $url->getTargetUrl() : $url]);
        }

        return $url instanceof SymfonyRedirect ? $url : Redirect::away($url);
    }
}
