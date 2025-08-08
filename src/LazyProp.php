<?php

namespace Inertia;

use Illuminate\Support\Facades\App;

/**
 * A property that is excluded from initial page loads but evaluated during partial reloads.
 *
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
