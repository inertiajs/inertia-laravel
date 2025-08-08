<?php

namespace Inertia;

use Illuminate\Support\Facades\App;

/**
 * A property that is loaded asynchronously after the initial page render.
 * Implements lazy loading for improved page performance.
 */
class DeferProp implements IgnoreFirstLoad, Mergeable
{
    use MergesProps;

    /**
     * The callback to resolve the property.
     *
     * @var callable
     */
    protected $callback;

    /**
     * The defer group.
     *
     * @var string|null
     */
    protected $group;

    /**
     * Create a new deferred property instance.
     */
    public function __construct(callable $callback, ?string $group = null)
    {
        $this->callback = $callback;
        $this->group = $group;
    }

    /**
     * Get the defer group.
     *
     * @return string|null
     */
    public function group()
    {
        return $this->group;
    }

    /**
     * Resolve the property value.
     *
     * @return mixed
     */
    public function __invoke()
    {
        return App::call($this->callback);
    }
}
