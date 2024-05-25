<?php

namespace Inertia;

use Closure;
use Illuminate\Support\Facades\App;

class AlwaysProp
{
    /** @var mixed */
    protected $value;

    /**
     * @param mixed $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    public function __invoke()
    {
        return $this->value instanceof Closure ? App::call($this->value) : $this->value;
    }
}
