<?php

namespace Inertia;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\View;
use Illuminate\Contracts\Support\Responsable;

class Response implements Responsable
{
    protected $rootView;
    protected $sharedProps;
    protected $version;
    protected $component;
    protected $props;
    protected $viewData = [];

    public function __construct($rootView, $sharedProps = [], $version, $component, $props)
    {
        $this->rootView = $rootView;
        $this->sharedProps = $sharedProps;
        $this->version = $version;
        $this->component = $component;
        $this->props = $props;
    }

    public function with($key, $value)
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
