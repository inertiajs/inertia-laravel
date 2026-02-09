<?php

namespace Inertia;

class MergeProp implements Mergeable, Onceable
{
    use MergesProps, ResolvesCallables, ResolvesOnce;

    /**
     * The property value.
     *
     * Merged with existing client-side data during partial reloads.
     */
    protected mixed $value;

    /**
     * Create a new merge property instance. Merge properties are combined
     * with existing client-side data during partial reloads instead of
     * completely replacing the property value.
     */
    public function __construct(mixed $value)
    {
        $this->value = $value;
        $this->merge = true;
    }

    /**
     * Resolve the property value.
     */
    public function __invoke(): mixed
    {
        return $this->resolveCallable($this->value);
    }
}
