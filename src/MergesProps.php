<?php

namespace Inertia;

use Illuminate\Support\Arr;

trait MergesProps
{
    /** Whether this property should be merged with existing data */
    protected bool $merge = false;

    /** Whether this property should be deep merged with existing data */
    protected bool $deepMerge = false;

    /** The keys to match on when merging */
    protected array $matchOn = [];

    /**
     * Enable merging for this property.
     */
    public function merge(): static
    {
        $this->merge = true;

        return $this;
    }

    /**
     * Enable deep merging for this property.
     */
    public function deepMerge(): static
    {
        $this->deepMerge = true;

        return $this->merge();
    }

    /**
     * Set the keys to match on when merging.
     */
    public function matchOn(string|array $matchOn): static
    {
        $this->matchOn = Arr::wrap($matchOn);

        return $this;
    }

    /**
     * Check if this property should be merged.
     */
    public function shouldMerge(): bool
    {
        return $this->merge;
    }

    /**
     * Check if this property should be deep merged.
     */
    public function shouldDeepMerge(): bool
    {
        return $this->deepMerge;
    }

    /**
     * Get the keys to match on when merging.
     */
    public function matchesOn(): array
    {
        return $this->matchOn;
    }
}
