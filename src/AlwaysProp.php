<?php

namespace Inertia;

class AlwaysProp
{
    use ResolvesCallables;

    protected $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function __invoke()
    {
        return $this->resolveCallable($this->value);
    }
}
