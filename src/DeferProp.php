<?php

namespace Inertia;

class DeferProp implements Deferrable, IgnoreFirstLoad, Mergeable, Onceable, Rescuable
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
     * Indicates if exceptions should be rescued during deferred resolution.
     *
     * @var bool
     */
    protected $rescue;

    /**
     * Create a new deferred property instance. Deferred properties are excluded
     * from the initial page load and only evaluated when requested by the
     * frontend, improving initial page performance.
     */
    public function __construct(callable $callback, ?string $group = null, bool $rescue = false)
    {
        $this->callback = $callback;
        $this->rescue = $rescue;
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

    /**
     * Determine if deferred resolution errors should be rescued.
     */
    public function shouldRescue(): bool
    {
        return $this->rescue;
    }
}
