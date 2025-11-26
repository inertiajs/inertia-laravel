<?php

namespace Inertia;

use Illuminate\Support\Facades\App;

class OnceProp implements Onceable
{
    use ResolvesOnce;

    /**
     * The callback to resolve the property.
     *
     * @var callable
     */
    protected $callback;

    /**
     * Custom key.
     */
    protected bool $forceResolve = false;

    /**
     * Create a new 'once' property instance.
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
        $this->once = true;
    }

    public function forceResolve(bool $value = true): static
    {
        $this->forceResolve = $value;

        return $this;
    }

    public function shouldForceResolve(): bool
    {
        return $this->forceResolve;
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
