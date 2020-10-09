{!! '<?php' !!}

namespace App\Http\Middleware;

use Inertia\Middleware;

class {{ $name }} extends Middleware
{
    /**
     * Determine the current Inertia asset version hash
     * used for automatic asset cache busting.
     *
     * {{ '@' }}see https://inertiajs.com/asset-versioning
     *
     * {{ '@' }}param  \Illuminate\Http\Request  $request
     * {{ '@' }}return string|null
     */
    public function version($request)
    {
        //
    }

    /**
     * Defines the Inertia properties that automatically
     * shared as of default. Can be overwritten.
     *
     * {{ '@' }}see https://inertiajs.com/shared-data
     *
     * {{ '@' }}param  \Illuminate\Http\Request  $request
     * {{ '@' }}return array
     */
    public function share($request)
    {
        return array_merge(parent::share($request), [
            //
        ]);
    }
}
