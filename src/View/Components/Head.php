<?php

namespace Inertia\View\Components;

use Closure;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use Inertia\Ssr\SsrState;

class Head extends Component
{
    public function render(): Closure
    {
        return function (array $data) {
            $response = app(SsrState::class)->dispatch();

            if ($response) {
                return new HtmlString($response->head);
            }

            return new HtmlString((string) $data['slot']);
        };
    }

    public function resolveView()
    {
        $view = $this->render();

        return fn (array $data = []) => $view($data);
    }
}
