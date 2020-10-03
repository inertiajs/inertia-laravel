<?php

namespace Inertia;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Response as BaseResponse;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Contracts\Support\Arrayable;

class ResponseFactory
{
    use Macroable;

    /**
     * @var string
     */
    protected $rootView = 'app';

    /**
     * @var mixed[]
     */
    protected $sharedProps = [];

    /**
     * @var mixed|null
     */
    protected $version = null;

    /**
     * @param string $name
     */
    public function setRootView($name)
    {
        $this->rootView = $name;
    }

    /**
     * @param string|mixed[] $key
     * @param mixed|null     $value
     */
    public function share($key, $value = null)
    {
        if (is_array($key)) {
            $this->sharedProps = array_merge($this->sharedProps, $key);
        } else {
            Arr::set($this->sharedProps, $key, $value);
        }
    }

    /**
     * @param string|null $key
     *
     * @return mixed|mixed[]
     */
    public function getShared($key = null)
    {
        if ($key) {
            return Arr::get($this->sharedProps, $key);
        }

        return $this->sharedProps;
    }

    /**
     * @param mixed|null $version
     */
    public function version($version)
    {
        $this->version = $version;
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        $version = $this->version instanceof Closure
            ? App::call($this->version)
            : $this->version;

        return (string) $version;
    }

    /**
     * @param string           $component
     * @param array|Arrayable  $props
     *
     * @return Response
     */
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

    /**
     * @param string $url
     *
     * @return \Illuminate\Http\Response
     */
    public function location($url)
    {
        return BaseResponse::make('', 409, ['X-Inertia-Location' => $url]);
    }
}
