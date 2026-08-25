<?php

namespace Inertia;

class OnceProp implements HasLiveUpdates, Onceable
{
    use ResolvesCallables, ResolvesOnce, SupportsLiveUpdates;

    /**
     * The callback to resolve the property.
     *
     * @var callable
     */
    protected $callback;

    /**
     * Create a new once property instance.
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
        $this->once = true;
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
