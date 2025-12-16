<?php

namespace Inertia;

/**
 * @deprecated Use OptionalProp instead for clearer semantics.
 */
class LazyProp implements IgnoreFirstLoad
{
    use ResolvesCallables;

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
