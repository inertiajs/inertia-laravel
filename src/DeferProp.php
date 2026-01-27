<?php

namespace Inertia;

class DeferProp implements Deferrable, IgnoreFirstLoad, Mergeable, Onceable
{
    use DefersProps, MergesProps, ResolvesCallables, ResolvesOnce;

    /**
     * The callback to resolve the property.
     *
     * Loaded asynchronously after initial page render for performance.
     *
     * @var callable
     */
    protected $callback;

    /**
     * Create a new deferred property instance. Deferred properties are excluded
     * from the initial page load and only evaluated when requested by the
     * frontend, improving initial page performance.
     */
    public function __construct(callable $callback, ?string $group = null)
    {
        $this->callback = $callback;
        $this->defer($group);
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
