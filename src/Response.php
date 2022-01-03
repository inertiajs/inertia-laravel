<?php

namespace Inertia;

use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Response as ResponseFactory;
use Illuminate\Support\Traits\Macroable;

class Response implements Responsable
{
    use Macroable;

    protected $component;
    protected $props;
    protected $rootView;
    protected $version;
    protected $viewData = [];

    /**
     * @param  string  $component
     * @param  array|Arrayable  $props
     * @param  string  $rootView
     * @param  string  $version
     */
    public function __construct(string $component, $props, string $rootView = 'app', string $version = '')
    {
        $this->component = $component;
        $this->props = $props instanceof Arrayable ? $props->toArray() : $props;
        $this->rootView = $rootView;
        $this->version = $version;
    }

    /**
     * @param  string|array  $key
     * @param  mixed|null  $value
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
     * @param  mixed|null  $value
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
        $only = array_filter(explode(',', $request->header('X-Inertia-Partial-Data', '')));

        $props = ($only && $request->header('X-Inertia-Partial-Component') === $this->component)
            ? Arr::only($this->props, $only)
            : array_filter($this->props, static function ($prop) {
                return ! ($prop instanceof LazyProp);
            });

        $props = static::resolvePropertyInstances($props, $request);

        foreach ($props as $key => $value) {
            if (str_contains($key, '.')) {
                data_set($props, $key, $value);
                unset($props[$key]);
            }
        }

        $page = [
            'component' => $this->component,
            'props' => $props,
            'url' => $request->getRequestUri(),
            'version' => $this->version,
        ];

        if ($request->header('X-Inertia')) {
            return new JsonResponse($page, 200, [
                'Vary' => 'Accept',
                'X-Inertia' => 'true',
            ]);
        }

        return ResponseFactory::view($this->rootView, $this->viewData + ['page' => $page]);
    }

    private static function resolvePropertyInstances($props, $request): array
    {
        array_walk_recursive($props, static function (&$prop) use ($request) {
            if ($prop instanceof LazyProp) {
                $prop = App::call($prop);
            }

            if ($prop instanceof Closure) {
                $prop = App::call($prop);
            }

            if ($prop instanceof PromiseInterface) {
                $prop = $prop->wait();
            }

            if ($prop instanceof ResourceResponse || $prop instanceof JsonResource) {
                $prop = $prop->toResponse($request)->getData(true);
            }

            if ($prop instanceof Arrayable) {
                $prop = $prop->toArray();
            }

            // to be able to handle nested props, we need to re-run
            // the same function again if the prop is an array.
            if (is_array($prop)) {
                $prop = self::resolvePropertyInstances($prop, $request);
            }
        });

        return $props;
    }
}
