<?php

namespace Inertia\Testing;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Testing\TestResponse;

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
            'X-Inertia-Partial-Data' => $this->props,
            'X-Inertia-Partial-Component' => $this->component,
            'X-Inertia-Version' => $this->version,
        ]);
    }
}
