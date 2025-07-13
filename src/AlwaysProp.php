<?php

namespace Inertia;

use Illuminate\Support\Facades\App;

class AlwaysProp
{
    /**
     * Create a new always property instance.
     */
    public function __construct(
        protected mixed $value,
    ) {}

    /**
     * Invoke the property to get its value.
     */
    public function __invoke()
    {
        return is_callable($this->value) ? App::call($this->value) : $this->value;
    }
}
