<?php

namespace Inertia\Tests\middleware;

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
     * Determines the current Inertia asset version hash.
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
     * Defines the Inertia props that are shared by default.
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
