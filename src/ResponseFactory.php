<?php

namespace Inertia;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response;

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
        $version = $this->version instanceof Closure
            ? App::call($this->version)
            : $this->version;

        return (string) $version;
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

    public function forceRedirect($path, $status = 302, $headers = [], $secure = null)
    {
        if (Request::hasHeader('X-Inertia')) {
            return Response::make('', 409, $headers)->header('X-Inertia-Location', $path);
        } else {
            return Redirect::to($path, $status, $headers, $secure);
        }
    }
}
