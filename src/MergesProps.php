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
     * Indicates if the property values should be appended or prepended.
     */
    protected bool $append = true;

    /**
     * The paths to append.
     *
     * @var array<int, string>
     */
    protected array $appendPaths = [];

    /**
     * The paths to prepend.
     *
     * @var array<int, string>
     */
    protected array $prependPaths = [];

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

    /**
     * Determine if the property values should be appended or prepended.
     */
    public function shouldAppend(): bool
    {
        return $this->append;
    }

    /**
     * Specify that the value should be appended, optionally providing a key to append and a property to match on.
     */
    public function append(string|bool $key = true, ?string $matchOn = null): self
    {
        match (true) {
            is_bool($key) => $this->append = $key,
            is_string($key) => $this->appendPaths[] = $key,
        };

        if (is_string($key) && $matchOn) {
            $this->matchOn("{$key}.{$matchOn}");
        }

        return $this;
    }

    /**
     * Specify that the value should be prepended, optionally providing a key to prepend and a property to match on.
     */
    public function prepend(string|bool $key = true, ?string $matchOn = null): self
    {
        match (true) {
            is_bool($key) => $this->append = ! $key,
            is_string($key) => $this->prependPaths[] = $key,
        };

        if (is_string($key) && $matchOn) {
            $this->matchOn("{$key}.{$matchOn}");
        }

        return $this;
    }

    /**
     * Determine if the property should merge at the root level (vs at specific paths).
     */
    public function hasNestedMergePaths(): bool
    {
        return count($this->appendPaths) > 0 || count($this->prependPaths) > 0;
    }

    /**
     * Get the paths to append.
     *
     * @return array<int, string>
     */
    public function appendPaths(): array
    {
        return $this->appendPaths;
    }

    /**
     * Get the paths to prepend.
     *
     * @return array<int, string>
     */
    public function prependPaths(): array
    {
        return $this->prependPaths;
    }
}
