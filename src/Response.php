<?php

namespace Inertia;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceResponse;
use Illuminate\Support\Facades\Response as ResponseFactory;

class Response implements Responsable
{
    use Macroable;

    protected $component;
    protected $props;
    protected $rootView;
    protected $version;
    protected $viewData = [];

    /**
     * @param array|Arrayable $props
     */
    public function __construct(string $component, $props, string $rootView = 'app', string $version = '')
    {
        $this->component = $component;
        $this->props = $props instanceof Arrayable ? $props->toArray() : $props;
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
        $only = array_filter(explode(',', $request->header('X-Inertia-Partial-Data', '')));

        $props = ($only && $request->header('X-Inertia-Partial-Component') === $this->component)
            ? Arr::only($this->props, $only)
            : array_filter($this->props, static function ($prop) {
                return ! ($prop instanceof LazyProp);
            });

        $props = $this->resolvePropertyInstances($props, $request);

        $page = [
            'component' => $this->component,
            'props' => $props,
            'url' => $request->getBaseUrl().$request->getRequestUri(),
            'version' => $this->version,
        ];

        if ($request->header('X-Inertia')) {
            return new JsonResponse($page, 200, ['X-Inertia' => 'true']);
        }

        return ResponseFactory::view($this->rootView, $this->viewData + ['page' => $page]);
    }

    /**
     * Resolve all necessary class instances in the given props.
     */
    public function resolvePropertyInstances(array $props, Request $request, bool $unpackDotProps = true): array
    {
        foreach ($props as $key => $value) {
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
                $value = $this->resolvePropertyInstances($value, $request, false);
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
}
