<?php

namespace Inertia;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Response as ResponseFactory;
use Inertia\Support\Header;

class Response implements Responsable
{
    use Macroable;

    protected $component;
    protected $props;
    protected $persisted;
    protected $rootView;
    protected $version;
    protected $viewData = [];

    /**
     * @param array|Arrayable $props
     */
    public function __construct(string $component, array $props, string $rootView = 'app', string $version = '', array $persisted = [])
    {
        $this->component = $component;
        $this->props = $props instanceof Arrayable ? $props->toArray() : $props;
        $this->persisted = $persisted;
        $this->rootView = $rootView;
        $this->version = $version;
    }

    /**
     * @param string|array $key
     * @param mixed        $value
     *
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
     * @param string|array $key
     * @param mixed        $value
     *
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
     * @param \Illuminate\Http\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        $props = $this->resolveProperties($request, $this->props);

        $page = [
            'component' => $this->component,
            'props' => $props,
            'url' => Str::start(Str::after($request->fullUrl(), $request->getSchemeAndHttpHost()), '/'),
            'version' => $this->version,
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
        $isPartial = $request->header(Header::PARTIAL_COMPONENT) === $this->component;

        if(! $isPartial) {
            $props = array_filter($this->props, static function ($prop) {
                return ! ($prop instanceof LazyProp);
            });
        }

        // Dot-notated props should be resolved last to ensure that
        // they are correctly merged with callable props.
        uksort($props, function ($key) {
            return str_contains($key, '.');
        });

        $only = $this->getOnly($request, $isPartial);
        $except = $this->getExcept($request, $isPartial);

        $props = $this->resolvePropertyInstances($props, $request, $only, $except);

        return $props;
    }

    /**
     * Get the `only` partial props.
     */
    public function getOnly(Request $request, bool $isPartial): array
    {
        if(! $isPartial) {
            return [];
        }

        return array_merge(
            array_filter(explode(',', $request->header(Header::PARTIAL_ONLY, ''))),
            $this->persisted
        );
    }

    /**
     * Get the `except` partial props.
     */
    public function getExcept(Request $request, bool $isPartial): array
    {
        if(! $isPartial) {
            return [];
        }

        return array_filter(explode(',', $request->header(Header::PARTIAL_EXCEPT, '')));
    }

    /**
     * Resolve all necessary class instances in the given props.
     */
    public function resolvePropertyInstances(array $props, Request $request, array $only, array $except, bool $unpackDotProps = true, string $parentKey = ''): array
    {
        foreach ($props as $key => $value) {
            $prop = $parentKey ? implode('.', [$parentKey, $key]) : $key;

            if($only && ! $this->isPropIncluded($only, $prop)) {
                unset($props[$key]);

                continue;
            }

            if($except && $this->isPropExcluded($except, $prop)) {
                unset($props[$key]);

                continue;
            }

            if ($value instanceof Closure) {
                $value = App::call($value);
            }

            if ($value instanceof LazyProp) {
                $value = App::call($value);
            }

            if ($value instanceof PromiseInterface) {
                $value = $value->wait();
            }

            if ($value instanceof ResourceResponse || $value instanceof JsonResource) {
                $value = $value->toResponse($request)->getData(true);
            }

            if ($value instanceof Arrayable) {
                $value = $value->toArray();
            }

            if (is_array($value)) {
                $value = $this->resolvePropertyInstances($value, $request, $only, $except, false, $prop);
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
     * Determine whether a prop should be included in the partial response.
     */
    public function isPropIncluded(array $only, string $prop): bool
    {
        foreach($only as $key) {
            if(Str::startsWith($key, $prop) || Str::startsWith($prop, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether a prop should be excluded from the partial response.
     */
    public function isPropExcluded(array $except, string $prop): bool
    {
        foreach($except as $key) {
            if(Str::startsWith($prop, $key)) {
                return true;
            }
        }

        return false;
    }
}
