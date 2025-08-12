<?php

namespace Inertia;

use Carbon\CarbonInterval;
use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Response as ResponseFactory;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use Inertia\Support\Header;

class Response implements Responsable
{
    use Macroable;

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
        $this->clearHistory = session()->pull('inertia.clear_history', false);
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
                return ! ($prop instanceof IgnoreFirstLoad);
            });
        }

        $only = array_filter(explode(',', $request->header(Header::PARTIAL_ONLY, '')));
        $except = array_filter(explode(',', $request->header(Header::PARTIAL_EXCEPT, '')));

        if (count($only)) {
            $newProps = [];

            foreach ($only as $key) {
                Arr::set($newProps, $key, Arr::get($props, $key));
            }

            $props = $newProps;
        }

        if ($except) {
            Arr::forget($props, $except);
        }

        return $props;
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
        $only = array_filter(explode(',', $request->header(Header::PARTIAL_ONLY, '')));

        $value = [];

        foreach ($only as $key) {
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
        $except = array_filter(explode(',', $request->header(Header::PARTIAL_EXCEPT, '')));

        Arr::forget($props, $except);

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
            $resolveViaApp = collect([
                Closure::class,
                LazyProp::class,
                OptionalProp::class,
                DeferProp::class,
                AlwaysProp::class,
                MergeProp::class,
            ])->first(fn ($class) => $value instanceof $class);

            if ($resolveViaApp) {
                $value = App::call($value);
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
     * Resolve merge props configuration for client-side prop merging.
     *
     * @return array<string, mixed>
     */
    public function resolveMergeProps(Request $request): array
    {
        $resetProps = array_filter(explode(',', $request->header(Header::RESET, '')));
        $onlyProps = array_filter(explode(',', $request->header(Header::PARTIAL_ONLY, '')));
        $exceptProps = array_filter(explode(',', $request->header(Header::PARTIAL_EXCEPT, '')));

        $mergeProps = collect($this->props)
            ->filter(fn ($prop) => $prop instanceof Mergeable)
            ->filter(fn ($prop) => $prop->shouldMerge())
            ->reject(fn ($_, $key) => in_array($key, $resetProps))
            ->filter(fn ($_, $key) => count($onlyProps) === 0 || in_array($key, $onlyProps))
            ->reject(fn ($_, $key) => in_array($key, $exceptProps));

        $deepMergeProps = $mergeProps
            ->filter(fn ($prop) => $prop->shouldDeepMerge())
            ->keys();

        $matchPropsOn = $mergeProps
            ->map(function (Mergeable $prop, $key) {
                return collect($prop->matchesOn())
                    ->map(fn ($strategy) => $key.'.'.$strategy)
                    ->toArray();
            })
            ->flatten()
            ->values();

        $mergeProps = $mergeProps
            ->filter(fn ($prop) => ! $prop->shouldDeepMerge())
            ->keys();

        return array_filter([
            'mergeProps' => $mergeProps->toArray(),
            'deepMergeProps' => $deepMergeProps->toArray(),
            'matchPropsOn' => $matchPropsOn->toArray(),
        ], fn ($prop) => count($prop) > 0);
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

        $deferredProps = collect($this->props)
            ->filter(function ($prop) {
                return $prop instanceof DeferProp;
            })
            ->map(function ($prop, $key) {
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
