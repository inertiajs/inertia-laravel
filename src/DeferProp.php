<?php

namespace Inertia;

use Illuminate\Support\Facades\App;

class DeferProp implements IgnoreFirstLoad, Mergeable
{
    use MergesProps;

    protected $callback;

    protected $group;

    public function __construct(callable $callback, ?string $group = null)
    {
        $this->callback = $callback;
        $this->group = $group;
    }

    public function group()
    {
        return $this->group;
    }

    public function __invoke()
    {
        return App::call($this->callback);
    }
}
