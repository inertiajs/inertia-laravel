<?php

namespace Inertia\View\Components;

use Closure;
use Illuminate\View\Component;
use Inertia\Ssr\SsrState;

class Head extends Component
{
    public function render(): Closure
    {
        return function (array $data) {
            $response = app(SsrState::class)->dispatch();

            if ($response) {
                return $response->head;
            }

            return (string) $data['slot'];
        };
    }
}
