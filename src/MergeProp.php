<?php

namespace Inertia;

use Illuminate\Support\Facades\App;

/**
 * A property that merges with existing client-side data during partial reloads.
 * Supports both shallow and deep merging strategies.
 */
class MergeProp implements Mergeable
{
    use MergesProps;

    /**
     * The property value.
     *
     * @var mixed
     */
    protected $value;

    /**
     * Create a new merge property instance.
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
        return is_callable($this->value) ? App::call($this->value) : $this->value;
    }
}
