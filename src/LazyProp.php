<?php

namespace Inertia;

/**
 * @deprecated Use OptionalProp instead for clearer semantics.
 */
class LazyProp implements IgnoreFirstLoad, Optionable
{
    use ResolvesCallables, OptionalProps;

    /**
     * The callback to resolve the property.
     *
     * @var callable
     */
    protected $callback;

    /**
     * Create a new lazy property instance.
     */
    public function __construct(callable $callback)
    {
        $this->optional = true;
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
