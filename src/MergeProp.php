<?php

namespace Inertia;

use Illuminate\Support\Facades\App;

class MergeProp implements Mergeable
{
    use MergesProps;

    /** @var mixed */
    protected $value;

    /**
     * @param  mixed  $value
     * @param  string[]  $mergeStrategies
     */
    public function __construct($value, array $mergeStrategies = [])
    {
        $this->value = $value;
        $this->merge = true;
        $this->mergeStrategies = $mergeStrategies;
    }

    public function __invoke()
    {
        return is_callable($this->value) ? App::call($this->value) : $this->value;
    }
}
