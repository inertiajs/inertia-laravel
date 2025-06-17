<?php

namespace Inertia;

use Illuminate\Http\Request;

class PropContext
{
    public function __construct(
        public string $key,
        public array $props,
        public Request $request
    ) {
        //
    }
}
