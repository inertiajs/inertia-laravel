<?php

namespace Inertia;

use Illuminate\Support\Arr;

trait MergesProps
{
    /**
     * Indicates if the property should be merged.
     */
    protected bool $merge = false;

    /**
     * Indicates if the property should be deep merged.
     */
    protected bool $deepMerge = false;

    /**
     * The properties to match on for merging.
     *
     * @var array<int, string>
     */
    protected array $matchOn = [];

    /**
     * Mark the property for merging.
     */
    public function merge(): static
    {
        $this->merge = true;

        return $this;
    }

    /**
     * Mark the property for deep merging.
     */
    public function deepMerge(): static
    {
        $this->deepMerge = true;

        return $this->merge();
    }

    /**
     * Set the properties to match on for merging.
     *
     * @param  string|array<int, string>  $matchOn
     */
    public function matchOn(string|array $matchOn): static
    {
        $this->matchOn = Arr::wrap($matchOn);

        return $this;
    }

    /**
     * Determine if the property should be merged.
     */
    public function shouldMerge(): bool
    {
        return $this->merge;
    }

    /**
     * Determine if the property should be deep merged.
     */
    public function shouldDeepMerge(): bool
    {
        return $this->deepMerge;
    }

    /**
     * Get the properties to match on for merging.
     *
     * @return array<int, string>
     */
    public function matchesOn(): array
    {
        return $this->matchOn;
    }
}
