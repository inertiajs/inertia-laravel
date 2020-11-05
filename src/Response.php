<?php

namespace Inertia;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Response as ResponseFactory;

class Response implements Responsable
{
    protected $component;
    protected $props;
    protected $rootView;
    protected $version;
    protected $viewData = [];
    protected $inlineable;
    protected $inlineBase;
    protected $inlineComponent;
    protected $inlineProps;

    public function __construct($component, $props, $rootView = 'app', $version = null)
    {
        $this->component = $component;
        $this->props = $props instanceof Arrayable ? $props->toArray() : $props;
        $this->rootView = $rootView;
        $this->version = $version;
    }

    public function inlineBase(callable $inlineBase)
    {
        $this->inlineBase = $inlineBase;

        return $this;
    }

    public function inline($component, $props = [])
    {
        $this->inlineComponent = $component;
        $this->inlineProps = $props;

        return $this;
    }

    public function inlineable($inlineable)
    {
        $this->inlineable = $inlineable;

        return $this;
    }

    public function with($key, $value = null)
    {
        if (is_array($key)) {
            $this->props = array_merge($this->props, $key);
        } else {
            $this->props[$key] = $value;
        }

        return $this;
    }

    public function withViewData($key, $value = null)
    {
        if (is_array($key)) {
            $this->viewData = array_merge($this->viewData, $key);
        } else {
            $this->viewData[$key] = $value;
        }

        return $this;
    }

    public function toResponse($request)
    {
        if (! $request->header('X-Inertia-Inline') && $this->inlineBase) {
            return App::call($this->inlineBase)
                ->inline($this->component, $this->props)
                ->toResponse($request);
        }

        $only = array_filter(explode(',', $request->header('X-Inertia-Partial-Data')));

        $props = ($only && $request->header('X-Inertia-Partial-Component') === $this->component)
            ? Arr::only($this->props, $only)
            : array_filter($this->props, function ($prop) {
                return ! ($prop instanceof LazyProp);
            });

        array_walk_recursive($props, function (&$prop) use ($request) {
            if ($prop instanceof LazyProp) {
                $prop = App::call($prop);
            }

            if ($prop instanceof Closure) {
                $prop = App::call($prop);
            }

            if ($prop instanceof Responsable) {
                $prop = $prop->toResponse($request)->getData();
            }

            if ($prop instanceof Arrayable) {
                $prop = $prop->toArray();
            }
        });

        $page = [
            'component' => $this->component,
            'props' => $props,
            'url' => $request->getRequestUri(),
            'inline' => $this->inlineComponent ? [
                'component' => $this->inlineComponent,
                'props' => $this->inlineProps,
                'url' => $request->getRequestUri(),
            ] : null,
            'version' => $this->version,
            'inlineable' => $this->inlineable,
        ];

        if ($request->header('X-Inertia')) {
            return new JsonResponse($page, 200, [
                'Vary' => 'Accept',
                'X-Inertia' => 'true',
            ]);
        }

        return ResponseFactory::view($this->rootView, $this->viewData + ['page' => $page]);
    }
}
