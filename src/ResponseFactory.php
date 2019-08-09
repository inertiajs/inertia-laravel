<?php

namespace Inertia;

use Closure;
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

    public function share($key, $value = null)
    {
        if (is_array($key)) {
            $this->sharedProps = array_merge($this->sharedProps, $key);
        } else {
            Arr::set($this->sharedProps, $key, $value);
        }
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
        return $this->version instanceof Closure ? App::call($this->version) : $this->version;
    }

    public function render($component, $props = [])
    {
        return new Response(
            $component,
            array_merge($this->sharedProps, $props),
            $this->rootView,
            $this->getVersion()
        );
    }
}
