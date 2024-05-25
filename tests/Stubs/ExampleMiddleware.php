<?php

namespace Inertia\Tests\Stubs;

use LogicException;
use Inertia\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExampleMiddleware extends Middleware
{
    /**
     * @var mixed
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
     */
    public function version(Request $request): ?string
    {
        return $this->version;
    }

    /**
     * Defines the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), $this->shared);
    }

    /**
     * Determines what to do when an Inertia action returned with no response.
     * By default, we'll redirect the user back to where they came from.
     */
    public function onEmptyResponse(Request $request, Response $response): Response
    {
        throw new LogicException('An empty Inertia response was returned.');
    }
}
