<?php

namespace Inertia;

use Illuminate\Support\Facades\App;

/**
 * A property that is always included in Inertia responses.
 * Bypasses partial reload filtering to ensure critical data is available.
 */
class AlwaysProp
{
    /**
     * The property value.
     *
     * @var mixed
     */
    protected $value;

    /**
     * Create a new always property instance.
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
        return is_callable($this->value) ? App::call($this->value) : $this->value;
    }
}
