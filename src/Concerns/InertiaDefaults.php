<?php

namespace Inertia\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

trait InertiaDefaults
{
    /**
     * Determine the current Inertia asset version hash
     * used for automatic asset cache busting.
     *
     * @see https://inertiajs.com/asset-versioning
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    public function version($request)
    {
        return null;
    }

    /**
     * Defines the Inertia properties that automatically
     * shared as of default. Can be overwritten.
     *
     * @see https://inertiajs.com/shared-data
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function share($request)
    {
        return [
            'errors' => function () {
                if (! Session::has('errors')) {
                    return (object) [];
                }

                return (object) Collection::make(Session::get('errors')->getBags())->map(function ($bag) {
                    return (object) Collection::make($bag->messages())->map(function ($errors) {
                        return $errors[0];
                    })->toArray();
                })->pipe(function ($bags) {
                    return $bags->has('default') ? $bags->get('default') : $bags->toArray();
                });
            },
        ];
    }
}
