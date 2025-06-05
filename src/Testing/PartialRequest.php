<?php

namespace Inertia\Testing;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Testing\TestResponse;
use Inertia\Support\Header;

class PartialRequest
{
    use MakesHttpRequests;

    public function __construct(
        protected string $props,
        protected string $url,
        protected string $component,
        protected string $version,
        protected Application $app
    ) {}

    public function __invoke(): TestResponse
    {
        return $this->get($this->url, [
            Header::PARTIAL_ONLY => $this->props,
            Header::PARTIAL_COMPONENT => $this->component,
            Header::VERSION => $this->version,
        ]);
    }
}
