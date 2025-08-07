<?php

namespace Inertia;

use Illuminate\Support\Arr;

trait MergesProps
{
    protected bool $merge = false;

    protected bool $deepMerge = false;

    protected array $matchOn = [];

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

    public function matchOn(string|array $matchOn): static
    {
        $this->matchOn = Arr::wrap($matchOn);

        return $this;
    }

    public function shouldMerge(): bool
    {
        return $this->merge;
    }

    public function shouldDeepMerge(): bool
    {
        return $this->deepMerge;
    }

    public function matchesOn(): array
    {
        return $this->matchOn;
    }
}
