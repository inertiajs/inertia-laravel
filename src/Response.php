<?php

namespace Inertia;

use BackedEnum;
use Carbon\CarbonInterval;
use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Response as ResponseFactory;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use Inertia\Support\Header;
use UnitEnum;

class Response implements Responsable
{
    use Macroable;
    use ResolvesCallables;

    /**
     * The name of the root component.
     *
     * @var string
     */
    protected $component;

    /**
     * The page props.
     *
     * @var array<string, mixed>
     */
    protected $props;

    /**
     * The name of the root view.
     *
     * @var string
     */
    protected $rootView;

    /**
     * The asset version.
     *
     * @var string
     */
    protected $version;

    /**
     * Indicates if the browser history should be cleared.
     *
     * @var bool
     */
    protected $clearHistory;

    /**
     * Indicates if the browser history should be encrypted.
     *
     * @var bool
     */
    protected $encryptHistory;

    /**
     * The view data.
     *
     * @var array<string, mixed>
     */
    protected $viewData = [];

    /**
     * The cache duration settings.
     *
     * @var array<int, mixed>
     */
    protected $cacheFor = [];

    /**
     * The URL resolver callback.
     */
    protected ?Closure $urlResolver = null;

    /**
     * Create a new Inertia response instance.
     *
     * @param  array<array-key, mixed|\Inertia\ProvidesInertiaProperties>  $props
     */
    public function __construct(
        string $component,
        array $props,
        string $rootView = 'app',
        string $version = '',
        bool $encryptHistory = false,
        ?Closure $urlResolver = null
    ) {
        $this->component = $component;
        $this->props = $props;
        $this->rootView = $rootView;
        $this->version = $version;
        $this->clearHistory = session()->pull(SessionKey::ClearHistory->value, false);
        $this->encryptHistory = $encryptHistory;
        $this->urlResolver = $urlResolver;
    }

    /**
     * Add additional properties to the page.
     *
     * @param  string|array<string, mixed>|ProvidesInertiaProperties  $key
     * @param  mixed  $value
     * @return $this
     */
    public function with($key, $value = null): self
    {
        if ($key instanceof ProvidesInertiaProperties) {
            $this->props[] = $key;
        } elseif (is_array($key)) {
            $this->props = array_merge($this->props, $key);
        } else {
            $this->props[$key] = $value;
        }

        return $this;
    }

    /**
     * Add additional data to the view.
     *
     * @param  string|array<string, mixed>  $key
     * @param  mixed  $value
     * @return $this
     */
    public function withViewData($key, $value = null): self
    {
        if (is_array($key)) {
            $this->viewData = array_merge($this->viewData, $key);
        } else {
            $this->viewData[$key] = $value;
        }

        return $this;
    }

    /**
     * Set the root view.
     *
     * @return $this
     */
    public function rootView(string $rootView): self
    {
        $this->rootView = $rootView;

        return $this;
    }

    /**
     * Set the cache duration for the response.
     *
     * @param  string|array<int, mixed>  $cacheFor
     * @return $this
     */
    public function cache(string|array $cacheFor): self
    {
        $this->cacheFor = is_array($cacheFor) ? $cacheFor : [$cacheFor];

        return $this;
    }

    /**
     * Add flash data to the response.
     *
     * @param  \BackedEnum|\UnitEnum|string|array<string, mixed>  $key
     * @return $this
     */
    public function flash(BackedEnum|UnitEnum|string|array $key, mixed $value = null): self
    {
        Inertia::flash($key, $value);

        return $this;
    }

    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        $props = $this->resolveProperties($request, $this->props);

        $page = array_merge(
            [
                'component' => $this->component,
                'props' => $props,
                'url' => $this->getUrl($request),
                'version' => $this->version,
                'clearHistory' => $this->clearHistory,
                'encryptHistory' => $this->encryptHistory,
            ],
            $this->resolveMergeProps($request),
            $this->resolveDeferredProps($request),
            $this->resolveCacheDirections($request),
            $this->resolveScrollProps($request),
            $this->resolveOnceProps($request),
            $this->resolveFlashData($request),
        );

        if ($request->header(Header::INERTIA)) {
            return new JsonResponse($page, 200, [Header::INERTIA => 'true']);
        }

        return ResponseFactory::view($this->rootView, $this->viewData + ['page' => $page]);
    }

    /**
     * Resolve the properties for the response.
     *
     * @param  array<array-key, mixed>  $props
     * @return array<string, mixed>
     */
    public function resolveProperties(Request $request, array $props): array
    {
        $props = $this->resolveInertiaPropsProviders($props, $request);
        $props = $this->resolvePartialProperties($props, $request);
        $props = $this->resolveOnceProperties($props, $request);
        $props = $this->resolveArrayableProperties($props, $request);
        $props = $this->resolveAlways($props);
        $props = $this->resolvePropertyInstances($props, $request);

        return $props;
    }

    /**
     * Resolve the ProvidesInertiaProperties props.
     *
     * @param  array<array-key, mixed>  $props
     * @return array<string, mixed>
     */
    public function resolveInertiaPropsProviders(array $props, Request $request): array
    {
        $newProps = [];

        $renderContext = new RenderContext($this->component, $request);

        foreach ($props as $key => $value) {
            if (is_numeric($key) && $value instanceof ProvidesInertiaProperties) {
                // Pipe into a Collection to leverage Collection::getArrayableItems()
                /** @var array<string, mixed> $inertiaProps */
                $inertiaProps = collect($value->toInertiaProperties($renderContext))->all();
                $newProps = array_merge($newProps, $inertiaProps);
            } else {
                $newProps[$key] = $value;
            }
        }

        return $newProps;
    }

    /**
     * Resolve properties for partial requests. Filters properties based on
     * 'only' and 'except' headers from the client, allowing for selective
     * data loading to improve performance.
     *
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    public function resolvePartialProperties(array $props, Request $request): array
    {
        if (! $this->isPartial($request)) {
            return array_filter($props, static function ($prop) {
                return ! ($prop instanceof IgnoreFirstLoad)
                    && ! ($prop instanceof Deferrable && $prop->shouldDefer());
            });
        }

        if ($only = $this->getOnlyProps($request)) {
            $newProps = [];

            foreach ($only as $key) {
                Arr::set($newProps, $key, Arr::get($props, $key));
            }

            $props = $newProps;
        }

        if ($except = $this->getExceptProps($request)) {
            Arr::forget($props, $except);
        }

        return $props;
    }

    /**
     * Resolve properties that should only be resolved once.
     *
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    public function resolveOnceProperties(array $props, Request $request): array
    {
        if (! $this->isInertia($request) || $this->isPartial($request)) {
            return $props;
        }

        $exceptOnceProps = $this->getExceptOnceProps($request);

        if (count($exceptOnceProps) === 0) {
            return $props;
        }

        return collect($props)
            ->reject(function ($prop, string $key) use ($exceptOnceProps) {
                if (! $prop instanceof Onceable) {
                    return false;
                }

                if (! $prop->shouldResolveOnce()) {
                    return false;
                }

                if ($prop->shouldBeRefreshed()) {
                    return false;
                }

                return in_array($prop->getKey() ?? $key, $exceptOnceProps);
            })
            ->all();
    }

    /**
     * Resolve arrayable properties and closures. Converts Arrayable objects
     * to arrays, evaluates closures, and handles dot notation properties
     * for nested data structures.
     *
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    public function resolveArrayableProperties(array $props, Request $request, bool $unpackDotProps = true): array
    {
        foreach ($props as $key => $value) {
            if ($value instanceof Closure) {
                $value = App::call($value);
            }

            if ($value instanceof Arrayable) {
                $value = $value->toArray();
            }

            if (is_array($value)) {
                $value = $this->resolveArrayableProperties($value, $request, false);
            }

            if ($unpackDotProps && str_contains($key, '.')) {
                Arr::set($props, $key, $value);
                unset($props[$key]);
            } else {
                $props[$key] = $value;
            }
        }

        return $props;
    }

    /**
     * Resolve the `only` partial request props.
     *
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    public function resolveOnly(Request $request, array $props): array
    {
        $value = [];

        foreach ($this->getOnlyProps($request) as $key) {
            Arr::set($value, $key, data_get($props, $key));
        }

        return $value;
    }

    /**
     * Resolve the `except` partial request props.
     *
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    public function resolveExcept(Request $request, array $props): array
    {
        Arr::forget($props, $this->getExceptProps($request));

        return $props;
    }

    /**
     * Resolve `always` properties that should always be included.
     *
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    public function resolveAlways(array $props): array
    {
        $always = array_filter($this->props, static function ($prop) {
            return $prop instanceof AlwaysProp;
        });

        return array_merge(
            $always,
            $props
        );
    }

    /**
     * Resolve all necessary class instances in the given props.
     *
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    public function resolvePropertyInstances(array $props, Request $request, ?string $parentKey = null): array
    {
        foreach ($props as $key => $value) {
            if ($value instanceof ScrollProp) {
                $value->configureMergeIntent($request);
            }

            $resolveViaApp = collect([
                Closure::class,
                LazyProp::class,
                OptionalProp::class,
                DeferProp::class,
                AlwaysProp::class,
                MergeProp::class,
                ScrollProp::class,
                OnceProp::class,
            ])->first(fn ($class) => $value instanceof $class);

            if ($resolveViaApp) {
                $value = $this->resolveCallable($value);
            }

            $currentKey = $parentKey ? $parentKey.'.'.$key : $key;

            if ($value instanceof ProvidesInertiaProperty) {
                $value = $value->toInertiaProperty(new PropertyContext($currentKey, $props, $request));
            }

            if ($value instanceof Arrayable) {
                $value = $value->toArray();
            }

            if ($value instanceof PromiseInterface) {
                $value = $value->wait();
            }

            if ($value instanceof Responsable) {
                $_response = $value->toResponse($request);

                if (method_exists($_response, 'getData')) {
                    $value = $_response->getData(true);
                }
            }

            if (is_array($value)) {
                $value = $this->resolvePropertyInstances($value, $request, $currentKey);
            }

            $props[$key] = $value;
        }

        return $props;
    }

    /**
     * Resolve the cache directions for the response.
     *
     * @return array<string, mixed>
     */
    public function resolveCacheDirections(Request $request): array
    {
        if (count($this->cacheFor) === 0) {
            return [];
        }

        return [
            'cache' => collect($this->cacheFor)->map(function ($value) {
                if ($value instanceof CarbonInterval) {
                    return $value->totalSeconds;
                }

                return intval($value);
            }),
        ];
    }

    /**
     * Parse props from request headers.
     *
     * @return array<int, string>|null
     */
    protected static function parsePropsFromHeader(Request $request, string $key): ?array
    {
        return array_filter(explode(',', $request->header($key, ''))) ?: null;
    }

    /**
     * Get the props that should be included based on the request headers when using 'only'.
     *
     * @return array<int, string>
     */
    protected function getOnlyProps(Request $request): ?array
    {
        return static::parsePropsFromHeader($request, Header::PARTIAL_ONLY);
    }

    /**
     * Get the props that should be excluded based on the request headers when using 'except'.
     *
     * @return array<int, string>
     */
    protected function getExceptProps(Request $request): ?array
    {
        return static::parsePropsFromHeader($request, Header::PARTIAL_EXCEPT);
    }

    /**
     * Get the props that should be reset based on the request headers.
     *
     * @return array<int, string>
     */
    public function getResetProps(Request $request): array
    {
        return static::parsePropsFromHeader($request, Header::RESET) ?? [];
    }

    /**
     * Get the props that have already been loaded once based on the request headers.
     *
     * @return array<int, string>
     */
    protected function getExceptOnceProps(Request $request): array
    {
        return static::parsePropsFromHeader($request, Header::EXCEPT_ONCE_PROPS) ?? [];
    }

    /**
     * Get the props that should be considered for merging based on the request headers.
     *
     * @return \Illuminate\Support\Collection<string, \Inertia\Mergeable>
     */
    protected function getMergePropsForRequest(Request $request, bool $rejectResetProps = true): Collection
    {
        return collect($this->props)
            ->filter(fn ($prop) => $prop instanceof Mergeable && $prop->shouldMerge())
            ->when($rejectResetProps, fn (Collection $props) => $props->except($this->getResetProps($request)))
            ->only($this->getOnlyProps($request))
            ->except($this->getExceptProps($request));
    }

    /**
     * Resolve merge props configuration for client-side prop merging.
     *
     * @return array<string, mixed>
     */
    public function resolveMergeProps(Request $request): array
    {
        $mergeProps = $this->getMergePropsForRequest($request);

        return array_filter([
            'mergeProps' => $this->resolveAppendMergeProps($mergeProps),
            'prependProps' => $this->resolvePrependMergeProps($mergeProps),
            'deepMergeProps' => $this->resolveDeepMergeProps($mergeProps),
            'matchPropsOn' => $this->resolveMergeMatchingKeys($mergeProps),
        ], fn ($prop) => count($prop) > 0);
    }

    /**
     * Resolve props that should be appended during merging.
     *
     * @param  \Illuminate\Support\Collection<string, \Inertia\Mergeable>  $mergeProps
     * @return array<int, string>
     */
    protected function resolveAppendMergeProps(Collection $mergeProps): array
    {
        [$rootAppendProps, $nestedAppendProps] = $mergeProps
            ->reject(fn (Mergeable $prop) => $prop->shouldDeepMerge())
            ->partition(fn (Mergeable $prop) => $prop->appendsAtRoot());

        return $nestedAppendProps
            ->flatMap(fn (Mergeable $prop, string $key) => collect($prop->appendsAtPaths())->map(fn ($path) => $key.'.'.$path))
            ->merge($rootAppendProps->keys())
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Resolve props that should be prepended during merging.
     *
     * @param  \Illuminate\Support\Collection<string, \Inertia\Mergeable>  $mergeProps
     * @return array<int, string>
     */
    protected function resolvePrependMergeProps(Collection $mergeProps): array
    {
        [$rootPrependProps, $nestedPrependProps] = $mergeProps
            ->reject(fn (Mergeable $prop) => $prop->shouldDeepMerge())
            ->partition(fn (Mergeable $prop) => $prop->prependsAtRoot());

        return $nestedPrependProps
            ->flatMap(fn (Mergeable $prop, string $key) => collect($prop->prependsAtPaths())->map(fn ($path) => $key.'.'.$path))
            ->merge($rootPrependProps->keys())
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Resolve props that should be deep merged.
     *
     * @param  \Illuminate\Support\Collection<string, \Inertia\Mergeable>  $mergeProps
     * @return array<int, string>
     */
    protected function resolveDeepMergeProps(Collection $mergeProps): array
    {
        return $mergeProps
            ->filter(fn (Mergeable $prop) => $prop->shouldDeepMerge())
            ->keys()
            ->toArray();
    }

    /**
     * Resolve the matching keys for merge props.
     *
     * @param  \Illuminate\Support\Collection<string, \Inertia\Mergeable>  $mergeProps
     * @return array<int, string>
     */
    protected function resolveMergeMatchingKeys(Collection $mergeProps): array
    {
        return $mergeProps
            ->map(function (Mergeable $prop, $key) {
                return collect($prop->matchesOn())
                    ->map(fn ($strategy) => $key.'.'.$strategy)
                    ->toArray();
            })
            ->flatten()
            ->values()
            ->toArray();
    }

    /**
     * Resolve deferred props configuration for client-side lazy loading.
     *
     * @return array<string, mixed>
     */
    public function resolveDeferredProps(Request $request): array
    {
        if ($this->isPartial($request)) {
            return [];
        }

        $exceptOnceProps = $this->getExceptOnceProps($request);

        $deferredProps = collect($this->props)
            ->filter(function ($prop) {
                return $prop instanceof Deferrable && $prop->shouldDefer();
            })
            ->reject(function (Deferrable $prop, string $key) use ($exceptOnceProps) {
                if (! $prop instanceof Onceable) {
                    return false;
                }

                if (! $prop->shouldResolveOnce()) {
                    return false;
                }

                if ($prop->shouldBeRefreshed()) {
                    return false;
                }

                return in_array($prop->getKey() ?? $key, $exceptOnceProps);
            })
            ->map(function (Deferrable $prop, $key) {
                return [
                    'key' => $key,
                    'group' => $prop->group(),
                ];
            })
            ->groupBy('group')
            ->map
            ->pluck('key');

        return $deferredProps->isNotEmpty() ? ['deferredProps' => $deferredProps->toArray()] : [];
    }

    /**
     * Resolve scroll props configuration for client-side infinite scrolling.
     *
     * @return array<string, mixed>
     */
    public function resolveScrollProps(Request $request): array
    {
        $resetProps = $this->getResetProps($request);
        $isPartial = $this->isPartial($request);

        $scrollProps = $this->getMergePropsForRequest($request, false)
            ->filter(fn (Mergeable $prop) => $prop instanceof ScrollProp)
            ->reject(fn (ScrollProp $prop) => ! $isPartial && $prop->shouldDefer())
            ->mapWithKeys(fn (ScrollProp $prop, string $key) => [$key => [
                ...$prop->metadata(),
                'reset' => in_array($key, $resetProps),
            ]]);

        return $scrollProps->isNotEmpty() ? ['scrollProps' => $scrollProps->toArray()] : [];
    }

    /**
     * Resolve props that should only be resolved once.
     *
     * @return array<string, array<int, string>>
     */
    public function resolveOnceProps(Request $request): array
    {
        $onceProps = collect($this->props)
            ->filter(fn ($prop) => $prop instanceof Onceable && $prop->shouldResolveOnce())
            ->only($this->getOnlyProps($request))
            ->except($this->getExceptProps($request))
            ->mapWithKeys(fn (Onceable $prop, string $key) => [$prop->getKey() ?? $key => [
                'prop' => $key,
                'expiresAt' => $prop->expiresAt(),
            ]]);

        return $onceProps->isNotEmpty() ? ['onceProps' => $onceProps->toArray()] : [];
    }

    /**
     * Resolve flash data from the session.
     *
     * @return array<string, mixed>
     */
    protected function resolveFlashData(Request $request): array
    {
        $flash = Inertia::getFlashed($request);

        return $flash ? ['flash' => $flash] : [];
    }

    /**
     * Determine if the request is an Inertia request.
     */
    public function isInertia(Request $request): bool
    {
        return (bool) $request->header(Header::INERTIA);
    }

    /**
     * Determine if the request is a partial request.
     */
    public function isPartial(Request $request): bool
    {
        return $request->header(Header::PARTIAL_COMPONENT) === $this->component;
    }

    /**
     * Get the URL from the request while preserving the trailing slash.
     */
    protected function getUrl(Request $request): string
    {
        $urlResolver = $this->urlResolver ?? function (Request $request) {
            $url = Str::start(Str::after($request->fullUrl(), $request->getSchemeAndHttpHost()), '/');

            $rawUri = Str::before($request->getRequestUri(), '?');

            return Str::endsWith($rawUri, '/') ? $this->finishUrlWithTrailingSlash($url) : $url;
        };

        return App::call($urlResolver, ['request' => $request]);
    }

    /**
     * Ensure the URL has a trailing slash before the query string.
     */
    protected function finishUrlWithTrailingSlash(string $url): string
    {
        // Make sure the relative URL ends with a trailing slash and re-append the query string if it exists.
        $urlWithoutQueryWithTrailingSlash = Str::finish(Str::before($url, '?'), '/');

        return str_contains($url, '?')
            ? $urlWithoutQueryWithTrailingSlash.'?'.Str::after($url, '?')
            : $urlWithoutQueryWithTrailingSlash;
    }
}
