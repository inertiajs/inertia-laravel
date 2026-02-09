<?php

namespace Inertia\Ssr;

class Response
{
    /**
     * The HTML head content from server-side rendering.
     */
    public string $head;

    /**
     * The HTML body content from server-side rendering.
     */
    public string $body;

    /**
     * Create a new SSR response instance.
     */
    public function __construct(string $head, string $body)
    {
        $this->head = $head;
        $this->body = $body;
    }
}
