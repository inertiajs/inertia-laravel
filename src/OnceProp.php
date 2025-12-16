<?php

namespace Inertia;

class OnceProp implements Onceable
{
    use ResolvesCallables, ResolvesOnce;

    /**
     * The callback to resolve the property.
     *
     * @var callable
     */
    protected $callback;

    /**
     * Create a new once property instance.
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
        $this->once = true;
    }

    /**
     * Resolve the property value.
     *
     * @return mixed
     */
    public function __invoke()
    {
        return $this->resolveCallable($this->callback);
    }
}
