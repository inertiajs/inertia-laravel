<?php

namespace Inertia;

use Illuminate\Broadcasting\Channel;

interface HasLiveUpdates
{
    /**
     * Mark the property as live, refreshing it whenever one of the given events is received.
     *
     * @param  object|class-string|string|array<int, object|class-string|string>|null  $on
     * @param  string|Channel|array<int, string|Channel>|null  $channel
     */
    public function live(mixed $on = null, string|Channel|array|null $channel = null, ?int $throttle = null): static;

    /**
     * Determine if the property should receive live updates.
     */
    public function isLive(): bool;

    /**
     * Get the live-update listeners that should refresh the property.
     *
     * @return array<int, array{channel: array{name: string, type: string}, events: array<int, string>}>
     */
    public function liveListeners(): array;

    /**
     * Get the number of milliseconds to throttle live updates by.
     */
    public function liveThrottle(): ?int;
}
