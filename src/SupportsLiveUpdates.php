<?php

namespace Inertia;

use Error;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\EncryptedPrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\HasBroadcastChannel;
use Illuminate\Support\Arr;
use LogicException;
use ReflectionClass;

trait SupportsLiveUpdates
{
    /**
     * Indicates if the property should receive live updates.
     */
    protected bool $live = false;

    /**
     * The events and channels each [live] call declared. Kept per call so an
     * explicit channel only ever applies to the events it was passed with.
     *
     * @var array<int, array{events: array<int, object|class-string|string>, channels: array<int, string|Channel>}>
     */
    protected array $liveDeclarations = [];

    /**
     * The number of milliseconds to throttle live updates by.
     */
    protected ?int $liveThrottle = null;

    /**
     * Mark the property as live. Live properties are refreshed with a partial
     * reload whenever one of the given events is received, keeping the page
     * in sync with the server without any user interaction. Merge properties
     * accumulate across responses, so a refresh would append to what is
     * already there rather than replace it, and cannot be live.
     *
     * @param  object|class-string|string|array<int, object|class-string|string>|null  $on
     * @param  string|Channel|array<int, string|Channel>|null  $channel
     */
    public function live(mixed $on = null, string|Channel|array|null $channel = null, ?int $throttle = null): static
    {
        if ($this instanceof Mergeable && $this->shouldMerge()) {
            throw new LogicException('Live updates are not supported on merge properties.');
        }

        $this->live = true;
        $this->liveDeclarations[] = ['events' => Arr::wrap($on), 'channels' => Arr::wrap($channel)];

        if ($throttle !== null) {
            $this->liveThrottle = $throttle;
        }

        return $this;
    }

    /**
     * Determine if the property should receive live updates.
     */
    public function isLive(): bool
    {
        return $this->live;
    }

    /**
     * Get the number of milliseconds to throttle live updates by.
     */
    public function liveThrottle(): ?int
    {
        return $this->liveThrottle;
    }

    /**
     * Get the live-update listeners that should refresh the property. A channel
     * passed to [live] overrides the ones its own events broadcast on, and
     * nothing else, so declarations never borrow each other's channels.
     *
     * @return array<int, array{channel: array{name: string, type: string}, events: array<int, string>}>
     */
    public function liveListeners(): array
    {
        $hasEvents = collect($this->liveDeclarations)->contains(fn ($declaration) => count($declaration['events']) > 0);

        if (! $hasEvents) {
            throw new LogicException('A live property must listen for at least one event. Pass the event to the [on] argument.');
        }

        $listeners = [];

        foreach ($this->liveDeclarations as $declaration) {
            $hasChannels = count($declaration['channels']) > 0;

            foreach ($declaration['events'] as $event) {
                $eventName = $this->resolveEventName($event);

                $channels = $hasChannels ? $declaration['channels'] : $this->channelsFromEvent($event);

                foreach ($channels as $channel) {
                    $this->addLiveListener($listeners, $this->resolveChannel($channel), [$eventName]);
                }
            }
        }

        return array_values($listeners);
    }

    /**
     * Resolve the name Laravel broadcasts the given event as.
     *
     * @param  object|class-string|string  $event
     */
    protected function resolveEventName(object|string $event): string
    {
        if (is_object($event)) {
            return method_exists($event, 'broadcastAs')
                ? $event->broadcastAs()
                : $event::class;
        }

        // A missing class would leave a listener that never fires. Names without
        // a namespace separator are literal, and may come from non-Laravel producers.
        if (str_contains($event, '\\') && ! class_exists($event)) {
            throw new LogicException("The [{$event}] event class does not exist. Pass an existing event class to the [on] argument, or its broadcast name as a string.");
        }

        // A broadcast name is read the same way the channels are, off an event
        // built without its payload, which is all a fixed name needs.
        if (class_exists($event) && (new ReflectionClass($event))->hasMethod('broadcastAs')) {
            try {
                return (new ReflectionClass($event))->newInstanceWithoutConstructor()->broadcastAs();
            } catch (Error) {
                throw new LogicException("Unable to resolve the broadcast name for the [{$event}] event because it builds it from its payload. Pass an event instance to the [on] argument instead of its class name.");
            }
        }

        return $event;
    }

    /**
     * Gather the channels the given event broadcasts on. A class name is built
     * without its payload, which is all an event needs when its channels do not
     * depend on one.
     *
     * @param  object|class-string|string  $event
     * @return array<int, string|Channel|HasBroadcastChannel>
     */
    protected function channelsFromEvent(object|string $event): array
    {
        if (! is_string($event)) {
            return $this->broadcastChannels($event);
        }

        if (! class_exists($event)) {
            throw new LogicException("Unable to resolve the channels for the [{$event}] event because it was given as a string. Pass the [channel] argument, or pass an event instance to the [on] argument.");
        }

        try {
            return $this->broadcastChannels((new ReflectionClass($event))->newInstanceWithoutConstructor());
        } catch (Error) {
            throw new LogicException("Unable to resolve the channels for the [{$event}] event because it builds them from its payload. Pass an event instance to the [on] argument, or pass the [channel] argument.");
        }
    }

    /**
     * Read the channels off an event instance.
     *
     * @return array<int, string|Channel|HasBroadcastChannel>
     */
    protected function broadcastChannels(object $event): array
    {
        if (! method_exists($event, 'broadcastOn')) {
            throw new LogicException('The ['.$event::class.'] event does not define a [broadcastOn] method. Pass the [channel] argument instead.');
        }

        $channels = Arr::wrap($event->broadcastOn());

        if (count($channels) === 0) {
            throw new LogicException('The ['.$event::class.'] event does not broadcast on any channels. Pass the [channel] argument instead.');
        }

        return $channels;
    }

    /**
     * @param  array<string, array{channel: array{name: string, type: string}, events: array<int, string>}>  $listeners
     * @param  array{name: string, type: string}  $channel
     * @param  array<int, string>  $events
     */
    protected function addLiveListener(array &$listeners, array $channel, array $events): void
    {
        $key = "{$channel['type']}:{$channel['name']}";

        $listeners[$key] ??= [
            'channel' => $channel,
            'events' => [],
        ];

        foreach ($events as $event) {
            if (! in_array($event, $listeners[$key]['events'], true)) {
                $listeners[$key]['events'][] = $event;
            }
        }
    }

    /**
     * Resolve the given channel into its unprefixed name and type. Laravel's
     * prefixes follow Pusher's convention, so transports get the type separately
     * and apply their own naming.
     *
     * @return array{name: string, type: string}
     */
    protected function resolveChannel(string|Channel|HasBroadcastChannel $channel): array
    {
        if ($channel instanceof HasBroadcastChannel) {
            $channel = $channel->broadcastChannel();
        }

        if (is_string($channel)) {
            // Laravel's broadcaster casts a channel to a string without adding a
            // prefix, so a bare string is a public channel wherever it is given.
            // Use `new PrivateChannel(...)` to subscribe privately.
            return ['name' => $channel, 'type' => 'public'];
        }

        $type = $this->channelType($channel);

        return [
            'name' => $this->channelName($channel),
            'type' => $type,
        ];
    }

    /**
     * Resolve the type of the given channel.
     */
    protected function channelType(Channel $channel): string
    {
        return match (true) {
            $channel instanceof EncryptedPrivateChannel => 'encrypted-private',
            $channel instanceof PresenceChannel => 'presence',
            $channel instanceof PrivateChannel => 'private',
            default => 'public',
        };
    }

    /**
     * Resolve the name of the given channel, without the prefix Laravel's channel class added to it.
     */
    protected function channelName(Channel $channel): string
    {
        $prefix = match (true) {
            $channel instanceof EncryptedPrivateChannel => 'private-encrypted-',
            $channel instanceof PresenceChannel => 'presence-',
            $channel instanceof PrivateChannel => 'private-',
            default => '',
        };

        return $prefix !== '' && str_starts_with($channel->name, $prefix)
            ? substr($channel->name, strlen($prefix))
            : $channel->name;
    }
}
