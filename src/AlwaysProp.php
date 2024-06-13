<?php

namespace Inertia;

use Illuminate\Support\Facades\App;

class AlwaysProp
{
    protected $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function __invoke()
    {
        return is_callable($this->value) ? App::call($this->value) : $this->value;
    }
}
