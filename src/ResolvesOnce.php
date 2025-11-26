<?php

namespace Inertia;

use DateInterval;
use DateTimeInterface;
use Illuminate\Support\InteractsWithTime;

trait ResolvesOnce
{
    use InteractsWithTime;

    /**
     * Indicates if the property should be evaluated only once.
     */
    protected bool $once = false;

    protected bool $forceResolve = false;

    protected ?int $ttl = null;

    protected ?string $key = null;

    /**
     * Get the custom key.
     */
    public function getKey(): ?string
    {
        return $this->key;
    }

    /**
     * Set a custom key.
     */
    public function as(string $key): static
    {
        $this->key = $key;

        return $this;
    }

    /**
     * Mark the property to be evaluated only once.
     */
    public function once(): static
    {
        $this->once = true;

        return $this;
    }

    /**
     * Mark the property to be evaluated on every request.
     */
    public function forceResolve(bool $value = true): static
    {
        $this->forceResolve = $value;

        return $this;
    }

    /**
     * Set the time to live (TTL) for the property.
     */
    public function until(DateTimeInterface|DateInterval|int $delay): static
    {
        $this->ttl = $this->secondsUntil($delay);

        return $this;
    }

    /**
     * Determine if the property should be evaluated only once.
     */
    public function shouldResolveOnce(): bool
    {
        return $this->once && ! $this->forceResolve;
    }

    /**
     * Get the TTL for the property.
     */
    public function getTtl(): ?int
    {
        return $this->ttl;
    }
}
