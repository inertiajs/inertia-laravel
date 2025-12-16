<?php

namespace Inertia;

class MergeProp implements Mergeable, Onceable
{
    use MergesProps, ResolvesCallables, ResolvesOnce;

    /**
     * The property value.
     *
     * Merged with existing client-side data during partial reloads.
     *
     * @var mixed
     */
    protected $value;

    /**
     * Create a new merge property instance. Merge properties are combined
     * with existing client-side data during partial reloads instead of
     * completely replacing the property value.
     *
     * @param  mixed  $value
     */
    public function __construct($value)
    {
        $this->value = $value;
        $this->merge = true;
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
