<?php

namespace Inertia;

use Illuminate\Support\Facades\App;

class OptionalProp implements IgnoreFirstLoad
{
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
        return App::call($this->callback);
    }
}
