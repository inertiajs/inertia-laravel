<?php

namespace Inertia;

use Closure;
use Illuminate\Support\Facades\App;

class DeferProp implements IgnoreFirstLoad, Mergeable
{
    use MergesProps;

    /**
     * Create a new deferred property instance.
     */
    public function __construct(
        protected Closure $callback,
        protected ?string $group = null,
    ) {}

    /**
     * Invoke the property to get its value.
     *
     * Executes the callback and returns the result.
     */
    public function __invoke()
    {
        return App::call($this->callback);
    }

    /**
     * Get the group name for this deferred property.
     */
    public function group(): ?string
    {
        return $this->group;
    }
}
