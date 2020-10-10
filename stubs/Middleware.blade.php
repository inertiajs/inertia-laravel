{!! '<?php' !!}

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class {{ $name }} extends Middleware
{
    /**
     * Determines the current Inertia asset version hash.
     *
     * {{ '@' }}see https://inertiajs.com/asset-versioning
     * {{ '@' }}param  \Illuminate\Http\Request  $request
     * {{ '@' }}return string|null
     */
    public function version(Request $request)
    {
        //
    }

    /**
     * Defines the Inertia props that are shared by default.
     *
     * {{ '@' }}see https://inertiajs.com/shared-data
     * {{ '@' }}param  \Illuminate\Http\Request  $request
     * {{ '@' }}return array
     */
    public function share(Request $request)
    {
        return array_merge(parent::share($request), [
            //
        ]);
    }
}
