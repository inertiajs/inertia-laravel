<?php

namespace Inertia\Ssr;

class Response
{
    /**
     * Prepare the Inertia Server Side Rendering (SSR) response.
     */
    public function __construct(
        public string $head,
        public string $body,
    ) {}
}
