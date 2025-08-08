<?php

namespace Inertia;

use Illuminate\Support\Facades\App;

/**
 * A property that is only included when explicitly requested via partial reloads.
 * Enables on-demand evaluation for performance optimization.
 */
class OptionalProp implements IgnoreFirstLoad
{
    /**
     * The callback to resolve the property.
     *
     * @var callable
     */
    protected $callback;

    /**
     * Create a new optional property instance.
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
