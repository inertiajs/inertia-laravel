<?php

namespace Inertia\Testing;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Testing\TestResponse;
use Inertia\Support\Header;

class ReloadRequest
{
    use MakesHttpRequests;

    /**
     * Create a new Inertia reload request instance.
     */
    public function __construct(
        protected string $url,
        protected string $component,
        protected string $version,
        protected ?string $only = null,
        protected ?string $except = null,
        protected ?Application $app = null
    ) {
        $this->app ??= app();
    }

    /**
     * Request the Inertia page as a partial reload.
     */
    public function __invoke(): TestResponse
    {
        $headers = [Header::VERSION => $this->version];

        if (! blank($this->only)) {
            $headers[Header::PARTIAL_COMPONENT] = $this->component;
            $headers[Header::PARTIAL_ONLY] = $this->only;
        }

        if (! blank($this->except)) {
            $headers[Header::PARTIAL_COMPONENT] = $this->component;
            $headers[Header::PARTIAL_EXCEPT] = $this->except;
        }

        return $this->get($this->url, $headers);
    }
}
