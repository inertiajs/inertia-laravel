<?php

namespace Inertia;

use Illuminate\Support\Facades\App;

class AlwaysProp
{
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
        return is_callable($this->value) ? App::call($this->value) : $this->value;
    }
}
