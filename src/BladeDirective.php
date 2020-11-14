<?php

namespace Inertia;

class BladeDirective
{
    public function render(string $id = 'app')
    {
        return new class($id) {
            private $id;

            private $page;

            public function __construct(string $id)
            {
                $this->id = $id;
            }

            public function withPage($page)
            {
                $this->page = $page;

                return $this;
            }

            public function __toString()
            {
                return '<div id="'.$this->id.'" data-page="'.e(json_encode($this->page)).'"></div>';
            }
        };
    }
}
