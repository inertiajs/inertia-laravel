<?php

namespace Inertia;

use BackedEnum;
use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response as BaseResponse;
use Illuminate\Support\Traits\Macroable;
use Inertia\Support\Header;
use Inertia\Support\SessionKey;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use UnitEnum;

class ResponseFactory
{
    use Macroable;

    /**
     * The name of the root view.
     */
    protected string $rootView = 'app';

    /**
     * The shared properties.
     *
     * @var array<string, mixed>
     */
    protected array $sharedProps = [];

    /**
     * The asset version.
     */
    protected Closure|string|null $version = null;

    /**
     * Indicates if the browser history should be cleared.
     */
    protected bool $clearHistory = false;

    /**
     * Indicates if the browser history should be encrypted.
     */
    protected ?bool $encryptHistory = null;

    /**
     * The URL resolver callback.
     */
    protected ?Closure $urlResolver = null;

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
     */
    public function share($key, mixed $value = null): void
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
     */
    public function getShared(?string $key = null, mixed $default = null): mixed
    {
        if ($key) {
            return Arr::get($this->sharedProps, $key, $default);
        }

        return $this->sharedProps;
    }

    /**
     * Flush all shared data.
     */
    public function flushShared(): void
    {
        $this->sharedProps = [];
    }

    /**
     * Set the asset version.
     */
    public function version(\Closure|string|null $version): void
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
        session([SessionKey::CLEAR_HISTORY => true]);
    }

    /**
     * Encrypt the browser history.
     */
    public function encryptHistory(bool $encrypt = true): void
    {
        $this->encryptHistory = $encrypt;
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
     */
    public function merge(mixed $value): MergeProp
    {
        return new MergeProp($value);
    }

    /**
     * Create a deep merge property.
     */
    public function deepMerge(mixed $value): MergeProp
    {
        return (new MergeProp($value))->deepMerge();
    }

    /**
     * Create an always property.
     */
    public function always(mixed $value): AlwaysProp
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
     * Create an once property.
     */
    public function once(callable $value): OnceProp
    {
        return new OnceProp($value);
    }

    /**
     * Create and share an once property.
     */
    public function shareOnce(string $key, callable $callback): OnceProp
    {
        return tap(new OnceProp($callback), fn ($prop) => $this->share($key, $prop));
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
     * @param  BackedEnum|UnitEnum|string  $component
     * @param  array<array-key, mixed>|\Illuminate\Contracts\Support\Arrayable<array-key, mixed>|ProvidesInertiaProperties  $props
     */
    public function render($component, $props = []): Response
    {
        $component = match (true) {
            $component instanceof BackedEnum => $component->value,
            $component instanceof UnitEnum => $component->name,
            default => $component,
        };

        if (! is_string($component)) {
            throw new InvalidArgumentException('Component argument must be of type string or a string BackedEnum');
        }

        if (config('inertia.pages.ensure_pages_exist', false)) {
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

    /**
     * Flash data to be included with the next response. Unlike regular props,
     * flash data is not persisted in the browser's history state, making it
     * ideal for one-time notifications like toasts or highlights.
     *
     * @param  \BackedEnum|\UnitEnum|string|array<string, mixed>  $key
     */
    public function flash(BackedEnum|UnitEnum|string|array $key, mixed $value = null): self
    {
        $flash = $key;

        if (! is_array($key)) {
            $key = match (true) {
                $key instanceof BackedEnum => $key->value,
                $key instanceof UnitEnum => $key->name,
                default => $key,
            };

            $flash = [$key => $value];
        }

        session()->now(SessionKey::FLASH_DATA, [
            ...$this->getFlashed(),
            ...$flash,
        ]);

        return $this;
    }

    /**
     * Create a new redirect response to the previous location.
     *
     * @param  array<string, string>  $headers
     */
    public function back(int $status = 302, array $headers = [], mixed $fallback = false): RedirectResponse
    {
        return Redirect::back($status, $headers, $fallback);
    }

    /**
     * Retrieve the flashed data from the session.
     *
     * @return array<string, mixed>
     */
    public function getFlashed(?HttpRequest $request = null): array
    {
        $request ??= request();

        return $request->hasSession() ? $request->session()->get(SessionKey::FLASH_DATA, []) : [];
    }
}
