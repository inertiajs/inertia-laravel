<?php

namespace Inertia;

use Illuminate\Support\Facades\App;

class AlwaysProp
{
    /** @var mixed */
    protected $value;

    /**
     * @param  mixed  $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    public function __invoke()
    {
        return is_callable($this->value) ? App::call($this->value) : $this->value;
    }
}
