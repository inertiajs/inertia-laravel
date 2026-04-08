<?php

namespace Inertia\Ssr;

class SsrState
{
    /**
     * The page data for the current request.
     *
     * @var array<string, mixed>
     */
    public array $page = [];

    /**
     * The SSR response for the current request.
     */
    public ?Response $response = null;

    /**
     * Whether the SSR gateway has been dispatched.
     */
    protected bool $dispatched = false;

    /**
     * Set the page data for the current request.
     *
     * @param  array<string, mixed>  $page
     */
    public function setPage(array $page): static
    {
        $this->page = $page;

        return $this;
    }

    /**
     * Dispatch the page to the SSR gateway, or return the cached response.
     */
    public function dispatch(): ?Response
    {
        if (! $this->dispatched) {
            $this->dispatched = true;
            $this->response = app(Gateway::class)->dispatch($this->page);
        }

        return $this->response;
    }
}
