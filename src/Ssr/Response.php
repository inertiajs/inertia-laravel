<?php

namespace Inertia\Ssr;

class Response
{
    /**
     * @var array
     */
    public $head;

    /**
     * @var string
     */
    public $body;

    public function __construct(array $head, string $body)
    {
        $this->head = $head;
        $this->body = $body;
    }
}
