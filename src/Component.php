<?php

namespace Inertia;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response;

class Component
{
    protected $rootView = 'app';

    protected $sharedProps = [];

    public function setRootView($name)
    {
        $this->rootView = $name;
    }

    public function render($component, $props = [])
    {
        array_walk_recursive($this->sharedProps, function (&$item, $key) {
            if (is_callable($item)) {
                $item = app()->call($item);
            }
        });

        if (Request::header('X-Inertia')) {
            return Response::json([
                'component' => $component,
                'props' => array_merge($this->sharedProps, $props),
                'url' => Request::getRequestUri(),
            ], 200, [
                'Vary' => 'Accept',
                'X-Inertia' => true,
            ]);
        }

        return View::make($this->rootView, [
            'component' => $component,
            'props' => array_merge($this->sharedProps, $props),
        ]);
    }

    public function share($key, $value)
    {
        return Arr::set($this->sharedProps, $key, $value);
    }
}
