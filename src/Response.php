<?php

namespace Inertia;

use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Response as ResponseFactory;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use Inertia\Support\Header;

class Response implements Responsable
{
    use Macroable;

    protected $component;

    protected $props;

    protected $rootView;

    protected $version;

    protected $clearHistory;

    protected $viewData = [];

    /**
     * @param  array|Arrayable  $props
     */
    public function __construct(string $component, array $props, string $rootView = 'app', string $version = '', bool $clearHistory = false)
    {
        $this->component = $component;
        $this->props = $props instanceof Arrayable ? $props->toArray() : $props;
        $this->rootView = $rootView;
        $this->version = $version;
        $this->clearHistory = $clearHistory;
    }

    /**
     * @param  string|array  $key
     * @param  mixed  $value
     * @return $this
     */
    public function with($key, $value = null): self
    {
        if (is_array($key)) {
            $this->props = array_merge($this->props, $key);
        } else {
            $this->props[$key] = $value;
        }

        return $this;
    }

    /**
     * @param  string|array  $key
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

    public function rootView(string $rootView): self
    {
        $this->rootView = $rootView;

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

        $page = [
            'component' => $this->component,
            'props' => $props,
            'url' => Str::start(Str::after($request->fullUrl(), $request->getSchemeAndHttpHost()), '/'),
            'meta' => $this->resolveMeta($request),
        ];

        if ($request->header(Header::INERTIA)) {
            return new JsonResponse($page, 200, [Header::INERTIA => 'true']);
        }

        return ResponseFactory::view($this->rootView, $this->viewData + ['page' => $page]);
    }

    /**
     * Resolve the properites for the response.
     */
    public function resolveProperties(Request $request, array $props): array
    {
        $isPartial = $this->isPartial($request);

        if (! $isPartial) {
            $props = array_filter($this->props, static function ($prop) {
                return ! ($prop instanceof IgnoreFirstLoad);
            });
        }

        $props = $this->resolveArrayableProperties($props, $request);

        if ($isPartial && $request->hasHeader(Header::PARTIAL_ONLY)) {
            $props = $this->resolveOnly($request, $props);
        }

        if ($isPartial && $request->hasHeader(Header::PARTIAL_EXCEPT)) {
            $props = $this->resolveExcept($request, $props);
        }

        $props = $this->resolveAlways($props);

        $props = $this->resolvePropertyInstances($props, $request);

        return $props;
    }

    /**
     * Resolve all arrayables properties into an array.
     */
    public function resolveArrayableProperties(array $props, Request $request, bool $unpackDotProps = true): array
    {
        foreach ($props as $key => $value) {
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
     */
    public function resolveExcept(Request $request, array $props): array
    {
        $except = array_filter(explode(',', $request->header(Header::PARTIAL_EXCEPT, '')));

        Arr::forget($props, $except);

        return $props;
    }

    /**
     * Resolve `always` properties that should always be included on all visits, regardless of "only" or "except" requests.
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
     */
    public function resolvePropertyInstances(array $props, Request $request): array
    {
        foreach ($props as $key => $value) {
            $resolveViaApp = collect([
                Closure::class,
                LazyProp::class,
                OptionalProp::class,
                DeferProp::class,
                AlwaysProp::class,
                WhenVisible::class,
            ])->first(fn ($class) => $value instanceof $class);

            if ($resolveViaApp) {
                $value = App::call($value);
            }

            if ($value instanceof PromiseInterface) {
                $value = $value->wait();
            }

            if ($value instanceof ResourceResponse || $value instanceof JsonResource) {
                $value = $value->toResponse($request)->getData(true);
            }

            if (is_array($value)) {
                $value = $this->resolvePropertyInstances($value, $request);
            }

            $props[$key] = $value;
        }

        return $props;
    }

    /**
     * Resolve the meta data for the response.
     */
    public function resolveMeta(Request $request): array
    {
        $meta = [
            'assetVersion' => $this->version,
            'clearHistory' => $this->clearHistory,
        ];

        if ($this->isPartial($request)) {
            return $meta;
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

        if ($deferredProps->isNotEmpty()) {
            $meta['deferredProps'] = $deferredProps->toArray();
        }

        return $meta;
    }

    /**
     * Determine if the request is a partial request.
     */
    public function isPartial(Request $request): bool
    {
        return $request->header(Header::PARTIAL_COMPONENT) === $this->component;
    }
}
