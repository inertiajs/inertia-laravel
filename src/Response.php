<?php

namespace Inertia;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;

class Response implements Responsable
{
    protected $component;
    protected $props;
    protected $rootView;
    protected $version;
    protected $viewData = [];
    protected $statusCode = 200;

    public function __construct($component, $props, $rootView = 'app', $version = null)
    {
        $this->component = $component;
        $this->props = $props instanceof Arrayable ? $props->toArray() : $props;
        $this->rootView = $rootView;
        $this->version = $version;
    }

    public function setStatusCode($statusCode)
    {
        $this->statusCode = $statusCode;

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
        $only = array_filter(explode(',', $request->header('X-Inertia-Partial-Data')));

        $props = ($only && $request->header('X-Inertia-Partial-Component') === $this->component)
            ? Arr::only($this->props, $only)
            : $this->props;

        array_walk_recursive($props, function (&$prop) {
            if ($prop instanceof Closure) {
                $prop = App::call($prop);
            }
        });

        $page = [
            'component' => $this->component,
            'props' => $props,
            'url' => $request->getRequestUri(),
            'version' => $this->version,
        ];

        if ($request->header('X-Inertia')) {
            return response()
                ->json(
                    $page,
                    $this->statusCode, [
                        'Vary' => 'Accept',
                        'X-Inertia' => 'true',
                    ]
                );
        }

        return response()
            ->view(
                $this->rootView,
                $this->viewData + ['page' => $page],
                $this->statusCode
            );
    }
}
