<?php

namespace Inertia;

class AlwaysProp
{
    use ResolvesCallables;

    /**
     * The property value.
     *
     * Always included in Inertia responses, bypassing partial reload filtering.
     */
    protected mixed $value;

    /**
     * Create a new always property instance. Always properties are included
     * in every Inertia response, even during partial reloads when only
     * specific props are requested.
     */
    public function __construct(mixed $value)
    {
        $this->value = $value;
    }

    /**
     * Resolve the property value.
     */
    public function __invoke(): mixed
    {
        return $this->resolveCallable($this->value);
    }
}
