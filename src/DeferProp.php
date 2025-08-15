<?php

namespace Inertia;

use Illuminate\Support\Facades\App;

class DeferProp implements IgnoreFirstLoad, Mergeable
{
    use MergesProps;

    /**
     * The callback to resolve the property.
     *
     * Loaded asynchronously after initial page render for performance.
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
     * Create a new deferred property instance. Deferred properties are excluded
     * from the initial page load and only evaluated when requested by the
     * frontend, improving initial page performance.
     */
    public function __construct(callable $callback, ?string $group = null)
    {
        $this->callback = $callback;
        $this->group = $group;
    }

    /**
     * Get the defer group for this property. Properties with the same group
     * are loaded together in a single request, allowing for efficient
     * batching of related deferred data.
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
