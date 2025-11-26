<?php

namespace Inertia;

use DateInterval;
use DateTimeInterface;

interface Onceable
{
    public function once(): static;

    public function shouldResolveOnce(): bool;

    //

    public function getKey(): ?string;

    public function as(string $key): static;

    //

    public function until(DateTimeInterface|DateInterval|int $delay): static;

    public function expiresAt(): ?int;
}
