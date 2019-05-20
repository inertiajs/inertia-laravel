<?php

namespace Inertia;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;

class ResponseFactory
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
        Arr::set($this->sharedProps, $key, $value);
    }

    public function getShared($key = null)
    {
        if ($key) {
            return Arr::get($this->sharedProps, $key);
        }

        return $this->sharedProps;
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

        return new Response($this->rootView, $this->sharedProps, $this->getVersion(), $component, $props);
    }
}
