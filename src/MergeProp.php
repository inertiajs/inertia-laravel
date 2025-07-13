<?php

namespace Inertia;

use Illuminate\Support\Facades\App;

class MergeProp implements Mergeable
{
    use MergesProps;

    /**
     * Create a new merge property instance.
     */
    public function __construct(
        protected mixed $value,
    ) {
        $this->merge = true;
    }

    /**
     * Invoke the property to get its value.
     *
     * If the value is callable, it will be executed and the result returned.
     * Otherwise, the value itself is returned.
     */
    public function __invoke()
    {
        return is_callable($this->value)
            ? App::call($this->value)
            : $this->value;
    }
}
