<?php

namespace Inertia;

use Illuminate\Support\Facades\App;

/**
 * @deprecated Use OptionalProp instead for clearer semantics.
 */
class LazyProp implements IgnoreFirstLoad
{
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
        return App::call($this->callback);
    }
}
