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

    protected string $rootView = 'app';

    protected array $sharedProps = [];

    protected Closure|string|null $version;

    protected bool $clearHistory = false;

    protected bool $encryptHistory;

    protected ?Closure $urlResolver;

    /**
     * For set the root view.
     */
    public function setRootView(string $name): void
    {
        $this->rootView = $name;
    }

    /**
     * Share data with all Inertia responses.
     */
    public function share(string|array|Arrayable $key, mixed $value = null): void
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
     * Get shared data by key or all shared data.
     */
    public function getShared(?string $key = null, mixed $default = null): mixed
    {
        if ($key) {
            return Arr::get($this->sharedProps, $key, $default);
        }

        return $this->sharedProps;
    }

    /**
     * Clear all shared data.
     */
    public function flushShared(): void
    {
        $this->sharedProps = [];
    }

    /**
     * Set the asset version for cache busting.
     */
    public function version(Closure|string|null $version): void
    {
        $this->version = $version;
    }

    /**
     * Get the current asset version.
     */
    public function getVersion(): string
    {
        $version = $this->version instanceof Closure
            ? App::call($this->version)
            : $this->version;

        return (string) $version;
    }

    /**
     * Set a custom URL resolver for Inertia responses.
     */
    public function resolveUrlUsing(?Closure $urlResolver = null): void
    {
        $this->urlResolver = $urlResolver;
    }

    /**
     * Clear the browser history on the next response.
     */
    public function clearHistory(): void
    {
        session(['inertia.clear_history' => true]);
    }

    /**
     * Enable or disable history encryption for sensitive data.
     */
    public function encryptHistory(bool $encrypt = true): void
    {
        $this->encryptHistory = $encrypt;
    }

    /**
     * Create a lazy-loaded property that only loads on subsequent requests.
     *
     * @deprecated Use `optional` instead.
     */
    public function lazy(callable $callback): LazyProp
    {
        return new LazyProp($callback);
    }

    /**
     * Create an optional property that only loads when explicitly requested.
     */
    public function optional(callable $callback): OptionalProp
    {
        return new OptionalProp($callback);
    }

    /**
     * Create a deferred property that loads after the initial page load.
     */
    public function defer(callable $callback, string $group = 'default'): DeferProp
    {
        return new DeferProp($callback, $group);
    }

    /**
     * Create a property that merges with existing data.
     */
    public function merge(mixed $value): MergeProp
    {
        return new MergeProp($value);
    }

    /**
     * Create a property that deeply merges with existing data.
     */
    public function deepMerge(mixed $value): MergeProp
    {
        return (new MergeProp($value))->deepMerge();
    }

    /**
     * Create a property that is always included in responses.
     */
    public function always(mixed $value): AlwaysProp
    {
        return new AlwaysProp($value);
    }

    /**
     * Render an Inertia response for the given component.
     *
     * @throws ComponentNotFoundException If the component doesn't exist and ensure_pages_exist is enabled
     */
    public function render(string $component, array|Arrayable $props = []): Response
    {
        if (config('inertia.ensure_pages_exist', false)) {
            $this->findComponentOrFail($component);
        }

        if ($props instanceof Arrayable) {
            $props = $props->toArray();
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
     * Redirect to a new location.
     */
    public function location(string|SymfonyRedirect $url): SymfonyResponse
    {
        if (Request::inertia()) {
            return BaseResponse::make(
                '',
                409,
                [
                    Header::LOCATION => $url instanceof SymfonyRedirect ? $url->getTargetUrl() : $url,
                ]
            );
        }

        return $url instanceof SymfonyRedirect ? $url : Redirect::away($url);
    }

    /**
     * Find the component and throw an exception if it doesn't exist.
     *
     * @throws ComponentNotFoundException
     */
    protected function findComponentOrFail(string $component): void
    {
        try {
            app('inertia.view-finder')->find($component);
        } catch (InvalidArgumentException) {
            throw new ComponentNotFoundException("Inertia page component [{$component}] not found.");
        }
    }
}
