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
    protected array $appendsAtPaths = [];

    /**
     * The paths to prepend.
     *
     * @var array<int, string>
     */
    protected array $prependsAtPaths = [];

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
     * Determine if the property should be appended at the root level.
     *
     * @return bool
     */
    public function appendsAtRoot()
    {
        return $this->append && $this->mergesAtRoot();
    }

    /**
     * Determine if the property should be prepended at the root level.
     *
     * @return bool
     */
    public function prependsAtRoot()
    {
        return ! $this->append && $this->mergesAtRoot();
    }

    /**
     * Determine if the property merges at the root level.
     */
    protected function mergesAtRoot(): bool
    {
        return count($this->appendsAtPaths) === 0 && count($this->prependsAtPaths) === 0;
    }

    /**
     * Specify that the value should be appended, optionally providing a key to append and a property to match on.
     *
     * @param  bool|string|array<array-key, string>  $path
     */
    public function append(bool|string|array $path = true, ?string $matchOn = null): static
    {
        match (true) {
            is_bool($path) => $this->append = $path,
            is_string($path) => $this->appendsAtPaths[] = $path,
            is_array($path) => collect($path)->each(
                fn ($value, $key) => is_numeric($key) ? $this->append($value) : $this->append($key, $value)
            ),
        };

        if (is_string($path) && $matchOn) {
            $this->matchOn([...$this->matchOn, "{$path}.{$matchOn}"]);
        }

        return $this;
    }

    /**
     * Specify that the value should be prepended, optionally providing a key to prepend and a property to match on.
     *
     * @param  bool|string|array<array-key, string>  $path
     */
    public function prepend(bool|string|array $path = true, ?string $matchOn = null): static
    {
        match (true) {
            is_bool($path) => $this->append = ! $path,
            is_string($path) => $this->prependsAtPaths[] = $path,
            is_array($path) => collect($path)->each(
                fn ($value, $key) => is_numeric($key) ? $this->prepend($value) : $this->prepend($key, $value)
            ),
        };

        if (is_string($path) && $matchOn) {
            $this->matchOn([...$this->matchOn, "{$path}.{$matchOn}"]);
        }

        return $this;
    }

    /**
     * Get the paths to append.
     *
     * @return array<int, string>
     */
    public function appendsAtPaths(): array
    {
        return $this->appendsAtPaths;
    }

    /**
     * Get the paths to prepend.
     *
     * @return array<int, string>
     */
    public function prependsAtPaths(): array
    {
        return $this->prependsAtPaths;
    }
}
