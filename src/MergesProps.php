<?php

namespace Inertia;

trait MergesProps
{
    protected bool $merge = false;

    protected bool $deepMerge = false;

    protected array $mergeStrategies = [];

    public function merge(): static
    {
        $this->merge = true;

        return $this;
    }

    public function deepMerge(): static
    {
        $this->deepMerge = true;

        return $this->merge();
    }

    public function shouldMerge(): bool
    {
        return $this->merge;
    }

    public function shouldDeepMerge(): bool
    {
        return $this->deepMerge;
    }

    public function mergeStrategies(): array
    {
        return $this->mergeStrategies;
    }
}
