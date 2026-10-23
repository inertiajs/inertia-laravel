<?php

namespace Inertia;

class AlwaysProp implements HasLiveUpdates
{
    use ResolvesCallables, SupportsLiveUpdates;

    /**
     * The property value.
     *
     * Always included in Inertia responses, bypassing partial reload filtering.
     *
     * @var mixed
     */
    protected $value;

    /**
     * Create a new always property instance. Always properties are included
     * in every Inertia response, even during partial reloads when only
     * specific props are requested.
     *
     * @param  mixed  $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * Resolve the property value.
     *
     * @return mixed
     */
    public function __invoke()
    {
        return $this->resolveCallable($this->value);
    }
}
