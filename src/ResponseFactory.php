<?php

namespace Inertia;

use BackedEnum;
use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response as BaseResponse;
use Illuminate\Support\Traits\Macroable;
use Inertia\Ssr\DisablesSsr;
use Inertia\Ssr\ExcludesSsrPaths;
use Inertia\Ssr\Gateway;
use Inertia\Support\Header;
use Inertia\Support\SessionKey;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use UnitEnum;

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
     * The component transformer callback.
     *
     * @var Closure|null
     */
    protected $componentTransformer;

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
     * @param  string|array<array-key, mixed>|Arrayable<array-key, mixed>|ProvidesInertiaProperties  $key
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
     * @param  Closure|string|null  $version
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
     * Set the component transformer.
     */
    public function transformComponentUsing(?Closure $componentTransformer = null): void
    {
        $this->componentTransformer = $componentTransformer;
    }

    /**
     * Clear the browser history on the next visit.
     */
    public function clearHistory(): void
    {
        session([SessionKey::CLEAR_HISTORY => true]);
    }

    /**
     * Preserve the URL fragment across the next redirect.
     */
    public function preserveFragment(): void
    {
        session([SessionKey::PRESERVE_FRAGMENT => true]);
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
     * Disable server-side rendering, optionally based on a condition.
     */
    public function disableSsr(Closure|bool $condition = true): void
    {
        $gateway = app(Gateway::class);

        if (! $gateway instanceof DisablesSsr) {
            throw new LogicException('The configured SSR gateway does not support disabling server-side rendering conditionally.');
        }

        $gateway->disable($condition);
    }

    /**
     * Exclude the given paths from server-side rendering.
     *
     * @param  array<int, string>|string  $paths
     */
    public function withoutSsr(array|string $paths): void
    {
        $gateway = app(Gateway::class);

        if (! $gateway instanceof ExcludesSsrPaths) {
            throw new LogicException('The configured SSR gateway does not support excluding paths from server-side rendering.');
        }

        $gateway->except($paths);
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

    /**
     * Transform the component name.
     *
     * @param  mixed  $component
     * @return mixed
     */
    protected function transformComponent($component)
    {
        if (! $this->componentTransformer) {
            return $component;
        }

        return ($this->componentTransformer)($component) ?? $component;
    }

    /**
     * Create an Inertia response.
     *
     * @param  BackedEnum|UnitEnum|string  $component
     * @param  array<array-key, mixed>|Arrayable<array-key, mixed>|ProvidesInertiaProperties  $props
     */
    public function render($component, $props = []): Response
    {
        $component = $this->transformComponent($component);

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
            $this->sharedProps,
            $props,
            $this->rootView,
            $this->getVersion(),
            $this->encryptHistory ?? config('inertia.history.encrypt', false),
            $this->urlResolver,
        );
    }

    /**
     * Create an Inertia location response.
     *
     * @param  string|RedirectResponse  $url
     */
    public function location($url): SymfonyResponse
    {
        if (Request::inertia()) {
            return BaseResponse::make('', 409, [Header::LOCATION => $url instanceof RedirectResponse ? $url->getTargetUrl() : $url]);
        }

        return $url instanceof RedirectResponse ? $url : Redirect::away($url);
    }

    /**
     * Register a callback to handle HTTP exceptions for Inertia requests.
     */
    public function handleExceptionsUsing(callable $callback): void
    {
        /** @var mixed $handler */
        $handler = app(ExceptionHandlerContract::class);

        if (! $handler instanceof ExceptionHandler) {
            if (app()->runningInConsole()) {
                return;
            }

            if (! method_exists($handler, 'respondUsing')) {
                throw new LogicException('The bound exception handler does not have a `respondUsing` method.');
            }
        }

        /** @var ExceptionHandler $handler */
        $handler->respondUsing(function ($response, $e, $request) use ($callback) {
            $result = $callback(new ExceptionResponse(
                $e,
                $request,
                $response,
                app(Router::class),
                app(Kernel::class),
            ));

            if ($result instanceof ExceptionResponse) {
                return $result->toResponse($request);
            }

            return $result ?? $response;
        });
    }

    /**
     * Flash data to be included with the next response. Unlike regular props,
     * flash data is not persisted in the browser's history state, making it
     * ideal for one-time notifications like toasts or highlights.
     *
     * @param  BackedEnum|UnitEnum|string|array<string, mixed>  $key
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

        session()->flash(SessionKey::FLASH_DATA, [
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

    /**
     * Retrieve and remove the flashed data from the session.
     *
     * @return array<string, mixed>
     */
    public function pullFlashed(?HttpRequest $request = null): array
    {
        $request ??= request();

        return $request->hasSession() ? $request->session()->pull(SessionKey::FLASH_DATA, []) : [];
    }
}
