<?php

namespace Inertia;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\View;
use Illuminate\Contracts\Support\Responsable;

class Response implements Responsable
{
    protected $component;
    protected $props;
    protected $rootView;
    protected $sharedProps;
    protected $version;
    protected $viewData = [];

    public function __construct($component, $props, $rootView = 'app', $sharedProps = [], $version = null)
    {
        $this->component = $component;
        $this->props = $props;
        $this->rootView = $rootView;
        $this->sharedProps = $sharedProps;
        $this->version = $version;
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

    public function withViewData($key, $value)
    {
        $this->viewData[$key] = $value;

        return $this;
    }

    public function toResponse($request)
    {
        $page = [
            'component' => $this->component,
            'props' => array_merge($this->sharedProps, $this->props),
            'url' => $request->getRequestUri(),
            'version' => $this->version,
        ];

        if ($request->header('X-Inertia')) {
            return new JsonResponse($page, 200, [
                'Vary' => 'Accept',
                'X-Inertia' => 'true',
            ]);
        }

        return View::make($this->rootView, $this->viewData + ['page' => $page]);
    }
}
