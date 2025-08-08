<?php

namespace Inertia;

use Illuminate\Support\Facades\App;

class DeferProp implements IgnoreFirstLoad, Mergeable
{
    use MergesProps;

    /**
     * @var callable
     */
    protected $callback;

    /**
     * @var string|null
     */
    protected $group;

    public function __construct(callable $callback, ?string $group = null)
    {
        $this->callback = $callback;
        $this->group = $group;
    }

    /**
     * @return string|null
     */
    public function group()
    {
        return $this->group;
    }

    /**
     * @return mixed
     */
    public function __invoke()
    {
        return App::call($this->callback);
    }
}
