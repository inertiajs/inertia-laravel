<?php

namespace Inertia;

trait DefersProps
{
    /**
     * Indicates if the property should be deferred.
     */
    protected bool $deferred = false;

    /**
     * The defer group.
     */
    protected ?string $deferGroup = null;

    /**
     * Mark this property as deferred. Deferred properties are excluded
     * from the initial page load and only evaluated when requested by the
     * frontend, improving initial page performance.
     */
    public function defer(?string $group = null): static
    {
        $this->deferred = true;
        $this->deferGroup = $group;

        return $this;
    }

    /**
     * Determine if this property should be deferred.
     */
    public function shouldDefer(): bool
    {
        return $this->deferred;
    }

    /**
     * Get the defer group for this property.
     *
     * @return string
     */
    public function group()
    {
        return $this->deferGroup ?? 'default';
    }
}
