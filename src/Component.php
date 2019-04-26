<?php

namespace Inertia;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response;

class Component
{
    protected $rootView = 'app';

    protected $sharedProps = [];

    protected $version = null;

    public function setRootView($name)
    {
        $this->rootView = $name;
    }

    public function share($key, $value)
    {
        return Arr::set($this->sharedProps, $key, $value);
    }

    public function get($key)
    {
        return Arr::get($this->sharedProps, $key);
    }

    public function version($version)
    {
        $this->version = $version;
    }

    public function getVersion()
    {
        return is_callable($this->version) ? App::call($this->version) : $this->version;
    }

    public function render($component, $props = [])
    {
        array_walk_recursive($this->sharedProps, function (&$item, $key) {
            if (is_callable($item)) {
                $item = App::call($item);
            }
        });

        $page = [
            'component' => $component,
            'props' => array_merge($this->sharedProps, $props),
            'url' => Request::fullUrl(),
            'version' => $this->getVersion(),
        ];

        if (Request::header('X-Inertia')) {
            return Response::json($page, 200, [
                'Vary' => 'Accept',
                'X-Inertia' => true,
            ]);
        }

        return View::make($this->rootView, array_merge(
            ['page' => $page], array_merge($this->sharedProps, $props)
        ));
    }
}
