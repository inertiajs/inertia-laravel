<?php

namespace Inertia;

interface Onceable
{
    public function once(): static;

    public function shouldResolveOnce(): bool;

    public function getKey(): ?string;

    public function as(string $key): static;
}
