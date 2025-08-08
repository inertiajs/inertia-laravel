<?php

namespace Inertia;

use Illuminate\Http\Request;

class PropertyContext
{
    /**
     * Create a new property context instance.
     *
     * @param  array<string, mixed>  $props
     */
    public function __construct(
        public string $key,
        public array $props,
        public Request $request
    ) {
        //
    }
}
