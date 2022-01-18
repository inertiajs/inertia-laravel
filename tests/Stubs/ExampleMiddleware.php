<?php

namespace Inertia\Tests\Stubs;

use Illuminate\Http\Request;
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
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    public function version(Request $request): ?string
    {
        return $this->version;
    }

    /**
     * Defines the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), $this->shared);
    }
}
