<?php

namespace Inertia\View\Components;

use Illuminate\View\Component;
use Inertia\Ssr\Response;
use Inertia\Ssr\SsrState;

class Head extends Component
{
    public ?Response $response;

    public function __construct()
    {
        $this->response = app(SsrState::class)->dispatch();
    }

    public function render(): string
    {
        return <<<'blade'
@if($response)
{!! $response->head !!}
@else
{!! $slot !!}
@endif
blade;
    }
}
