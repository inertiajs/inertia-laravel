<?php

namespace Inertia;

use BackedEnum;
use DateInterval;
use DateTimeInterface;
use Illuminate\Support\InteractsWithTime;
use UnitEnum;

trait ResolvesOnce
{
    use InteractsWithTime;

    /**
     * Indicates if the prop should be resolved only once.
     */
    protected bool $once = false;

    /**
     * Indicates if the prop should be forcefully refreshed.
     */
    protected bool $refresh = false;

    /**
     * The expiration time in seconds.
     */
    protected ?int $ttl = null;

    /**
     * The custom key for resolving the once prop.
     */
    protected ?string $key = null;

    /**
     * Mark the prop to be resolved only once.
     */
    public function once(bool $value = true, ?string $as = null, DateTimeInterface|DateInterval|int|null $until = null): static
    {
        $this->once = $value;

        if ($as !== null) {
            $this->as($as);
        }

        if ($until !== null) {
            $this->until($until);
        }

        return $this;
    }

    /**
     * Determine if the prop should be resolved only once.
     */
    public function shouldResolveOnce(): bool
    {
        return $this->once;
    }

    /**
     * Determine if the prop should be forcefully refreshed.
     */
    public function shouldBeRefreshed(): bool
    {
        return $this->refresh;
    }

    /**
     * Get the custom key for resolving the once prop.
     */
    public function getKey(): ?string
    {
        return $this->key;
    }

    /**
     * Set a custom key for resolving the once prop.
     */
    public function as(BackedEnum|UnitEnum|string $key): static
    {
        $this->key = match (true) {
            $key instanceof BackedEnum => $key->value,
            $key instanceof UnitEnum => $key->name,
            default => $key,
        };

        return $this;
    }

    /**
     * Mark the property to be forcefully sent to the client.
     */
    public function fresh(bool $value = true): static
    {
        $this->refresh = $value;

        return $this;
    }

    /**
     * Set the expiration for the once prop.
     */
    public function until(DateTimeInterface|DateInterval|int $delay): static
    {
        $this->ttl = $this->secondsUntil($delay);

        return $this;
    }

    /**
     * Get the expiration timestamp in milliseconds for the once prop.
     */
    public function expiresAt(): ?int
    {
        if ($this->ttl === null) {
            return null;
        }

        return $this->availableAt($this->ttl) * 1000;
    }
}
