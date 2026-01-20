<?php

namespace Inertia;

class OptionalProp implements IgnoreFirstLoad, Onceable
{
    use ResolvesCallables, ResolvesOnce;

    /**
     * The callback to resolve the property.
     *
     * Only included when explicitly requested via partial reloads.
     *
     * @var callable
     */
    protected $callback;

    /**
     * Create a new optional property instance. Optional properties are only
     * included when explicitly requested via partial reloads, reducing
     * initial payload size and improving performance.
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
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
