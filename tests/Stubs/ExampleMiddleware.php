<?php

namespace Inertia\Tests\Stubs;

use Inertia\Middleware;

class ExampleMiddleware extends Middleware
{
    /**
     * @var mixed|null
     */
    protected $version;

    /**
     * @var array|mixed
     */
    protected $shared = [];

    public function __construct($version = null, $shared = [])
    {
        $this->version = $version;
        $this->shared = $shared;
    }

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    public function version($request)
    {
        return $this->version;
    }

    /**
     * Defines the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function share($request)
    {
        return array_merge(parent::share($request), $this->shared);
    }
}
