<?php

namespace Inertia;

class LazyProp
{
    use ResolvesCallables;

    protected $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function __invoke()
    {
        return $this->resolveCallable($this->callback);
    }
}
