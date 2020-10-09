<?php

namespace Inertia\Tests\middleware;

use Inertia\Concerns\InertiaDefaults;
use Inertia\Middleware;

class ExampleMiddleware extends Middleware
{
    use InertiaDefaults {
        share as defaultShare;
        version as defaultVersion;
    }

    /**
     * @var mixed|null
     */
    protected $version;

    /**
     * @var array|mixed
     */
    protected $shared;

    public function __construct($version = null, $shared = [])
    {
        $this->version = $version;
        $this->shared = $shared;
    }

    /**
     * Determine the current Inertia asset version hash
     * used for automatic asset cache busting.
     *
     * @see https://inertiajs.com/asset-versioning
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    public function version($request)
    {
        return $this->version;
    }

    /**
     * Defines the Inertia properties that automatically
     * shared as of default. Can be overwritten.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function share($request)
    {
        return array_merge($this->defaultShare($request), $this->shared);
    }
}
