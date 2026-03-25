<?php

namespace Inertia\View\Components;

use Closure;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use Inertia\Ssr\SsrState;

class App extends Component
{
    public function __construct(
        public string $id = 'app',
    ) {}

    public function render(): Closure
    {
        return function () {
            $state = app(SsrState::class);
            $response = $state->dispatch();

            if ($response) {
                return new HtmlString($response->body);
            }

            return new HtmlString('<script data-page="'.$this->id.'" type="application/json">'.json_encode($state->page).'</script><div id="'.$this->id.'"></div>');
        };
    }

    public function resolveView()
    {
        $view = $this->render();

        return fn (array $data = []) => $view($data);
    }
}
