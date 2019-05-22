<?php

namespace Inertia;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;

class ResponseFactory
{
    protected $rootView = 'app';
    protected $sharedProps = [];
    protected $sharedPropsCallbacks = [];
    protected $version = null;

    public function setRootView($name)
    {
        $this->rootView = $name;
    }

    public function share($key, $value = null)
    {
        if ($key instanceof Closure) {
            $this->sharedPropsCallbacks[] = $key;
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
        return is_callable($this->version) ? App::call($this->version) : $this->version;
    }

    public function render($component, $props = [])
    {
        $props = array_merge($this->sharedProps, $props);

        foreach ($this->sharedPropsCallbacks as $callback) {
            $props = array_merge($props, App::call($callback));
        }

        array_walk_recursive($props, function (&$prop) {
            if (is_callable($prop)) {
                $prop = App::call($prop);
            }
        });

        return new Response($component, $props, $this->rootView, $this->getVersion());
    }
}
