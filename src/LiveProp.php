<?php

namespace Inertia;

use Illuminate\Broadcasting\Channel;

class LiveProp implements HasLiveUpdates
{
    use ResolvesCallables, SupportsLiveUpdates;

    /**
     * The property value.
     *
     * Refreshed with a partial reload whenever one of its events is received.
     *
     * @var mixed
     */
    protected $value;

    /**
     * Create a new live property instance. Live properties are refreshed with
     * a partial reload whenever one of the given events is received, keeping
     * the page in sync with the server without any user interaction.
     *
     * @param  mixed  $value
     * @param  object|class-string|string|array<int, object|class-string|string>|null  $on
     * @param  string|Channel|array<int, string|Channel>|null  $channel
     */
    public function __construct($value, mixed $on = null, string|Channel|array|null $channel = null, ?int $throttle = null)
    {
        $this->value = $value;
        $this->live($on, $channel, $throttle);
    }

    /**
     * Resolve the property value.
     *
     * @return mixed
     */
    public function __invoke()
    {
        return $this->resolveCallable($this->value);
    }
}
