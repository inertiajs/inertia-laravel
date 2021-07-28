<?php

namespace Inertia;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Response as BaseResponse;
use Illuminate\Support\Traits\Macroable;

class ResponseFactory
{
    use Macroable;

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
        } elseif ($key instanceof Arrayable) {
            $this->sharedProps = array_merge($this->sharedProps, $key->toArray());
        } else {
            Arr::set($this->sharedProps, $key, $value);
        }
    }

    public function getShared($key = null, $default = null)
    {
        if ($key) {
            return Arr::get($this->sharedProps, $key, $default);
        }

        return $this->sharedProps;
    }

    public function flushShared()
    {
        $this->sharedProps = [];
    }

    public function version($version)
    {
        $this->version = $version;
    }

    public function getVersion()
    {
        $version = $this->version instanceof Closure
            ? App::call($this->version)
            : $this->version;

        return (string) $version;
    }

    public function lazy(callable $callback)
    {
        return new LazyProp($callback);
    }

    public function render($component, $props = [])
    {
        if ($props instanceof Arrayable) {
            $props = $props->toArray();
        }

        return new Response(
            $component,
            array_merge($this->sharedProps, $props),
            $this->rootView,
            $this->getVersion()
        );
    }

    public function location($url)
    {
        return BaseResponse::make('', 409, ['X-Inertia-Location' => $url]);
    }
}
