<?php

namespace Inertia\Tests;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\EncryptedPrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\HasBroadcastChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\AlwaysProp;
use Inertia\DeferProp;
use Inertia\HasLiveUpdates;
use Inertia\Inertia;
use Inertia\LiveProp;
use Inertia\MergeProp;
use Inertia\OnceProp;
use Inertia\OptionalProp;
use Inertia\Response;
use Inertia\ScrollProp;
use LogicException;

class OrderUpdated
{
    public function __construct(public int $id = 1) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('orders.'.$this->id);
    }
}

class UserUpdated
{
    public function __construct(public int $id = 7) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('users.'.$this->id);
    }
}

class OrderPaid
{
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('orders.1');
    }
}

class OrderShipped
{
    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('stats'), new PresenceChannel('room.1')];
    }
}

class OrderCreated
{
    public function broadcastOn(): Channel
    {
        return new Channel('orders');
    }

    public function broadcastAs(): string
    {
        return 'order.created';
    }
}

class OrderArchived
{
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('orders');
    }

    public function broadcastAs(): string
    {
        return 'order.archived';
    }
}

class OrderRestored
{
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('orders');
    }
}

class OrderSecured
{
    public function broadcastOn(): EncryptedPrivateChannel
    {
        return new EncryptedPrivateChannel('orders.1');
    }
}

class OrderWithoutChannels
{
    //
}

class OrderWithEmptyChannels
{
    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [];
    }
}

class OrderBroadcastOnString
{
    /**
     * @return array<int, string>
     */
    public function broadcastOn(): array
    {
        return ['orders'];
    }
}

class OrderBroadcastOnMixed
{
    /**
     * @return array<int, string|Channel>
     */
    public function broadcastOn(): array
    {
        return ['orders', new PrivateChannel('secret')];
    }
}

class BroadcastableOrder implements HasBroadcastChannel
{
    public function broadcastChannelRoute(): string
    {
        return 'orders.{order}';
    }

    public function broadcastChannel(): string
    {
        return 'orders.9';
    }
}

class OrderBroadcastOnModel
{
    public function broadcastOn(): BroadcastableOrder
    {
        return new BroadcastableOrder;
    }
}

class LivePropTest extends TestCase
{
    public function test_can_invoke_with_a_value(): void
    {
        $liveProp = new LiveProp('A live prop value', new OrderUpdated);

        $this->assertSame('A live prop value', $liveProp());
    }

    public function test_can_invoke_with_a_callback(): void
    {
        $liveProp = new LiveProp(fn () => 'A live prop value', new OrderUpdated);

        $this->assertSame('A live prop value', $liveProp());
    }

    public function test_can_resolve_bindings_when_invoked(): void
    {
        $liveProp = new LiveProp(fn (Request $request) => $request, new OrderUpdated);

        $this->assertInstanceOf(Request::class, $liveProp());
    }

    public function test_string_function_names_are_not_invoked(): void
    {
        $liveProp = new LiveProp('date', new OrderUpdated);

        $this->assertSame('date', $liveProp());
    }

    public function test_the_factory_creates_a_live_prop(): void
    {
        $liveProp = Inertia::live('bar', on: new OrderUpdated);

        $this->assertInstanceOf(LiveProp::class, $liveProp);
        $this->assertTrue($liveProp->isLive());
    }

    public function test_props_are_not_live_by_default(): void
    {
        $this->assertFalse((new AlwaysProp('bar'))->isLive());
        $this->assertFalse((new OptionalProp(fn () => 'bar'))->isLive());
        $this->assertFalse((new OnceProp(fn () => 'bar'))->isLive());
        $this->assertFalse((new DeferProp(fn () => 'bar'))->isLive());
        $this->assertFalse(is_a(MergeProp::class, HasLiveUpdates::class, true));
        $this->assertFalse(is_a(ScrollProp::class, HasLiveUpdates::class, true));
    }

    public function test_supported_prop_types_can_be_marked_as_live(): void
    {
        $props = [
            new AlwaysProp('bar'),
            new OptionalProp(fn () => 'bar'),
            new OnceProp(fn () => 'bar'),
            new DeferProp(fn () => 'bar'),
        ];

        foreach ($props as $prop) {
            $result = $prop->live(on: new OrderUpdated);

            $this->assertSame($prop, $result);
            $this->assertTrue($prop->isLive());
            $this->assertSame([
                [
                    'channel' => ['name' => 'orders.1', 'type' => 'private'],
                    'events' => [OrderUpdated::class],
                ],
            ], $prop->liveListeners());
        }
    }

    public function test_the_event_class_name_is_used_as_the_event_name(): void
    {
        $liveProp = new LiveProp('bar', new OrderUpdated);

        $this->assertSame([OrderUpdated::class], $liveProp->liveListeners()[0]['events']);
    }

    public function test_the_broadcast_as_name_is_used_as_the_event_name(): void
    {
        $liveProp = new LiveProp('bar', new OrderCreated);

        $this->assertSame(['order.created'], $liveProp->liveListeners()[0]['events']);
    }

    public function test_can_listen_for_multiple_events(): void
    {
        $liveProp = new LiveProp('bar', [new OrderUpdated, new OrderShipped]);

        $this->assertSame([
            [
                'channel' => ['name' => 'orders.1', 'type' => 'private'],
                'events' => [OrderUpdated::class],
            ],
            [
                'channel' => ['name' => 'stats', 'type' => 'public'],
                'events' => [OrderShipped::class],
            ],
            [
                'channel' => ['name' => 'room.1', 'type' => 'presence'],
                'events' => [OrderShipped::class],
            ],
        ], $liveProp->liveListeners());
    }

    public function test_events_on_different_channels_keep_their_own_listener_pairs(): void
    {
        $liveProp = new LiveProp('bar', [new OrderUpdated, new UserUpdated]);

        $this->assertSame([
            [
                'channel' => ['name' => 'orders.1', 'type' => 'private'],
                'events' => [OrderUpdated::class],
            ],
            [
                'channel' => ['name' => 'users.7', 'type' => 'private'],
                'events' => [UserUpdated::class],
            ],
        ], $liveProp->liveListeners());
    }

    public function test_events_on_the_same_channel_collapse_into_one_listener(): void
    {
        $liveProp = new LiveProp('bar', [new OrderUpdated, new OrderPaid]);

        $this->assertSame([
            [
                'channel' => ['name' => 'orders.1', 'type' => 'private'],
                'events' => [OrderUpdated::class, OrderPaid::class],
            ],
        ], $liveProp->liveListeners());
    }

    public function test_one_event_broadcasting_on_several_channels_gets_one_listener_per_channel(): void
    {
        $liveProp = new LiveProp('bar', new OrderShipped);

        $this->assertSame([
            [
                'channel' => ['name' => 'stats', 'type' => 'public'],
                'events' => [OrderShipped::class],
            ],
            [
                'channel' => ['name' => 'room.1', 'type' => 'presence'],
                'events' => [OrderShipped::class],
            ],
        ], $liveProp->liveListeners());
    }

    public function test_explicit_channels_pair_every_event_with_every_channel(): void
    {
        $liveProp = new LiveProp('bar', [new OrderUpdated, new UserUpdated], ['orders.1', new Channel('stats')]);

        $this->assertSame([
            [
                'channel' => ['name' => 'orders.1', 'type' => 'private'],
                'events' => [OrderUpdated::class, UserUpdated::class],
            ],
            [
                'channel' => ['name' => 'stats', 'type' => 'public'],
                'events' => [OrderUpdated::class, UserUpdated::class],
            ],
        ], $liveProp->liveListeners());
    }

    public function test_duplicate_events_and_channels_are_removed(): void
    {
        $liveProp = new LiveProp('bar', [new OrderUpdated(1), new OrderUpdated(1)]);

        $this->assertSame([
            [
                'channel' => ['name' => 'orders.1', 'type' => 'private'],
                'events' => [OrderUpdated::class],
            ],
        ], $liveProp->liveListeners());
    }

    public function test_the_channel_type_is_resolved_from_the_channel_class(): void
    {
        $this->assertSame(
            [['channel' => ['name' => 'stats', 'type' => 'public'], 'events' => [OrderUpdated::class]]],
            (new LiveProp('bar', new OrderUpdated, new Channel('stats')))->liveListeners()
        );

        $this->assertSame(
            [['channel' => ['name' => 'orders.1', 'type' => 'private'], 'events' => [OrderUpdated::class]]],
            (new LiveProp('bar', new OrderUpdated, new PrivateChannel('orders.1')))->liveListeners()
        );

        $this->assertSame(
            [['channel' => ['name' => 'room.1', 'type' => 'presence'], 'events' => [OrderUpdated::class]]],
            (new LiveProp('bar', new OrderUpdated, new PresenceChannel('room.1')))->liveListeners()
        );

        $this->assertSame(
            [['channel' => ['name' => 'orders.1', 'type' => 'encrypted-private'], 'events' => [OrderUpdated::class]]],
            (new LiveProp('bar', new OrderUpdated, new EncryptedPrivateChannel('orders.1')))->liveListeners()
        );
    }

    public function test_the_channel_prefix_is_never_broadcast(): void
    {
        $liveProp = new LiveProp('bar', new OrderSecured);

        $this->assertSame(['name' => 'orders.1', 'type' => 'encrypted-private'], $liveProp->liveListeners()[0]['channel']);
    }

    public function test_a_public_channel_keeps_a_name_that_looks_prefixed(): void
    {
        $liveProp = new LiveProp('bar', new OrderUpdated, new Channel('private-orders.1'));

        $this->assertSame([['channel' => ['name' => 'private-orders.1', 'type' => 'public'], 'events' => [OrderUpdated::class]]], $liveProp->liveListeners());
    }

    public function test_a_bare_string_channel_is_private(): void
    {
        $liveProp = new LiveProp('bar', new OrderUpdated, 'orders.1');

        $this->assertSame([['channel' => ['name' => 'orders.1', 'type' => 'private'], 'events' => [OrderUpdated::class]]], $liveProp->liveListeners());
    }

    public function test_can_subscribe_to_multiple_channels(): void
    {
        $liveProp = new LiveProp('bar', new OrderUpdated, ['orders.1', new Channel('stats')]);

        $this->assertSame([
            ['channel' => ['name' => 'orders.1', 'type' => 'private'], 'events' => [OrderUpdated::class]],
            ['channel' => ['name' => 'stats', 'type' => 'public'], 'events' => [OrderUpdated::class]],
        ], $liveProp->liveListeners());
    }

    public function test_the_given_channel_wins_over_the_channels_the_event_broadcasts_on(): void
    {
        $liveProp = new LiveProp('bar', new OrderUpdated, 'other-orders.1');

        $this->assertSame([['channel' => ['name' => 'other-orders.1', 'type' => 'private'], 'events' => [OrderUpdated::class]]], $liveProp->liveListeners());
    }

    public function test_can_listen_for_an_event_class_name(): void
    {
        $liveProp = new LiveProp('bar', OrderRestored::class, 'orders.1');

        $this->assertSame([
            [
                'channel' => ['name' => 'orders.1', 'type' => 'private'],
                'events' => [OrderRestored::class],
            ],
        ], $liveProp->liveListeners());
    }

    public function test_an_event_class_name_that_defines_a_broadcast_name_throws(): void
    {
        $liveProp = new LiveProp('bar', OrderArchived::class, 'orders.1');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The ['.OrderArchived::class.'] event defines a [broadcastAs] method, which can only be resolved from an instance.');

        $liveProp->liveListeners();
    }

    public function test_an_event_class_name_that_does_not_exist_throws(): void
    {
        $liveProp = new LiveProp('bar', 'App\Events\OrderUpdated', 'orders.1');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The [App\Events\OrderUpdated] event class does not exist.');

        $liveProp->liveListeners();
    }

    public function test_an_event_name_without_a_namespace_is_left_untouched(): void
    {
        $liveProp = new LiveProp('bar', ['order.updated', 'OrderUpdated'], 'orders.1');

        $this->assertSame(['order.updated', 'OrderUpdated'], $liveProp->liveListeners()[0]['events']);
    }

    public function test_a_string_event_without_a_channel_throws(): void
    {
        $liveProp = new LiveProp('bar', OrderRestored::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unable to resolve the channels for the ['.OrderRestored::class.'] event because it was given as a string.');

        $liveProp->liveListeners();
    }

    public function test_an_event_without_channels_throws(): void
    {
        $liveProp = new LiveProp('bar', new OrderWithoutChannels);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The ['.OrderWithoutChannels::class.'] event does not define a [broadcastOn] method.');

        $liveProp->liveListeners();
    }

    public function test_an_event_that_broadcasts_on_no_channels_throws(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The ['.OrderWithEmptyChannels::class.'] event does not broadcast on any channels.');

        (new LiveProp('bar', new OrderWithEmptyChannels))->liveListeners();
    }

    public function test_a_live_prop_without_events_throws(): void
    {
        $liveProp = new LiveProp('bar');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A live property must listen for at least one event.');

        $liveProp->liveListeners();
    }

    public function test_the_throttle_is_null_by_default(): void
    {
        $liveProp = new LiveProp('bar', new OrderUpdated);

        $this->assertNull($liveProp->liveThrottle());
    }

    public function test_can_set_the_throttle(): void
    {
        $liveProp = Inertia::live('bar', on: new OrderUpdated, throttle: 5000);

        $this->assertSame(5000, $liveProp->liveThrottle());
    }

    public function test_unrelated_merge_and_live_props_can_render_together(): void
    {
        $page = $this->renderPage([
            'orders' => Inertia::merge(['bar']),
            'metrics' => Inertia::live(fn () => ['count' => 1], on: 'order.created', channel: 'metrics'),
        ]);

        $this->assertSame(['bar'], $page['props']['orders']);
        $this->assertSame(['count' => 1], $page['props']['metrics']);
        $this->assertSame(['orders'], $page['mergeProps']);
        $this->assertArrayHasKey('metrics', $page['liveProps']);
    }

    public function test_live_can_be_called_more_than_once(): void
    {
        $prop = (new LiveProp('bar', new OrderUpdated))->live(on: new OrderShipped);

        $this->assertSame([
            [
                'channel' => ['name' => 'orders.1', 'type' => 'private'],
                'events' => [OrderUpdated::class],
            ],
            [
                'channel' => ['name' => 'stats', 'type' => 'public'],
                'events' => [OrderShipped::class],
            ],
            [
                'channel' => ['name' => 'room.1', 'type' => 'presence'],
                'events' => [OrderShipped::class],
            ],
        ], $prop->liveListeners());
    }

    public function test_an_explicit_channel_does_not_leak_into_a_later_declaration(): void
    {
        $prop = (new LiveProp('bar', new OrderUpdated, 'orders.1'))->live(on: new OrderShipped);

        $this->assertSame([
            [
                'channel' => ['name' => 'orders.1', 'type' => 'private'],
                'events' => [OrderUpdated::class],
            ],
            [
                'channel' => ['name' => 'stats', 'type' => 'public'],
                'events' => [OrderShipped::class],
            ],
            [
                'channel' => ['name' => 'room.1', 'type' => 'presence'],
                'events' => [OrderShipped::class],
            ],
        ], $prop->liveListeners());
    }

    public function test_a_later_explicit_channel_does_not_leak_into_an_earlier_declaration(): void
    {
        $prop = (new LiveProp('bar', new OrderUpdated))->live(on: new OrderShipped, channel: 'stats');

        $this->assertSame([
            [
                'channel' => ['name' => 'orders.1', 'type' => 'private'],
                'events' => [OrderUpdated::class],
            ],
            [
                'channel' => ['name' => 'stats', 'type' => 'private'],
                'events' => [OrderShipped::class],
            ],
        ], $prop->liveListeners());
    }

    public function test_a_channel_declared_without_an_event_contributes_nothing(): void
    {
        $prop = (new LiveProp('bar', new OrderUpdated))->live(channel: 'ignored');

        $this->assertSame([
            [
                'channel' => ['name' => 'orders.1', 'type' => 'private'],
                'events' => [OrderUpdated::class],
            ],
        ], $prop->liveListeners());
    }

    public function test_a_bare_string_from_broadcast_on_is_public(): void
    {
        // Laravel casts these to strings and broadcasts them publicly, so
        // resolving them as private would listen on a dead channel
        $liveProp = new LiveProp('bar', new OrderBroadcastOnString);

        $this->assertSame([
            [
                'channel' => ['name' => 'orders', 'type' => 'public'],
                'events' => [OrderBroadcastOnString::class],
            ],
        ], $liveProp->liveListeners());
    }

    public function test_broadcast_on_can_mix_bare_strings_and_channel_objects(): void
    {
        $liveProp = new LiveProp('bar', new OrderBroadcastOnMixed);

        $this->assertSame([
            [
                'channel' => ['name' => 'orders', 'type' => 'public'],
                'events' => [OrderBroadcastOnMixed::class],
            ],
            [
                'channel' => ['name' => 'secret', 'type' => 'private'],
                'events' => [OrderBroadcastOnMixed::class],
            ],
        ], $liveProp->liveListeners());
    }

    public function test_broadcast_on_can_return_a_broadcastable_model(): void
    {
        $liveProp = new LiveProp('bar', new OrderBroadcastOnModel);

        $this->assertSame([
            [
                'channel' => ['name' => 'orders.9', 'type' => 'public'],
                'events' => [OrderBroadcastOnModel::class],
            ],
        ], $liveProp->liveListeners());
    }

    public function test_a_rescued_live_prop_does_not_re_emit_its_listeners(): void
    {
        $page = $this->renderPage([
            'orders' => Inertia::defer(fn () => throw new \RuntimeException('boom'), rescue: true)->live(on: new OrderCreated),
        ], [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'User/Edit',
            'X-Inertia-Partial-Data' => 'orders',
        ]);

        // The resolver threw, so there is no fresh channel to report. The client
        // keeps the listeners it already received, because an omitted prop means
        // preserve rather than unsubscribe.
        $this->assertContains('orders', $page['rescuedProps']);
        $this->assertArrayNotHasKey('orders', $page['liveProps'] ?? []);
    }

    public function test_a_live_prop_that_is_rescued_still_announces_its_listeners_on_the_initial_load(): void
    {
        $page = $this->renderPage([
            'orders' => Inertia::defer(fn () => throw new \RuntimeException('boom'), rescue: true)->live(on: new OrderCreated),
        ]);

        // The deferred prop is excluded from the initial load before anything is
        // resolved, so the client learns what to listen for up front
        $this->assertArrayHasKey('orders', $page['liveProps']);
    }

    public function test_the_live_manifest_is_serialized_into_the_page(): void
    {
        $page = $this->renderPage([
            'order' => Inertia::live(fn () => 'bar', on: new OrderUpdated),
        ]);

        $this->assertSame('bar', $page['props']['order']);

        $this->assertSame([
            'order' => [
                'listeners' => [
                    [
                        'channel' => ['name' => 'orders.1', 'type' => 'private'],
                        'events' => [OrderUpdated::class],
                    ],
                ],
            ],
        ], $page['liveProps']);
        $this->assertArrayNotHasKey('throttle', $page['liveProps']['order']);
    }

    public function test_the_live_manifest_serializes_a_set_throttle(): void
    {
        $page = $this->renderPage([
            'order' => Inertia::live(fn () => 'bar', on: new OrderUpdated, throttle: 500),
        ]);

        $this->assertSame([
            'listeners' => [
                [
                    'channel' => ['name' => 'orders.1', 'type' => 'private'],
                    'events' => [OrderUpdated::class],
                ],
            ],
            'throttle' => 500,
        ], $page['liveProps']['order']);
    }

    public function test_the_live_props_key_is_omitted_when_no_props_are_live(): void
    {
        $page = $this->renderPage(['order' => 'bar']);

        $this->assertArrayNotHasKey('liveProps', $page);
    }

    public function test_a_live_prop_returned_from_a_closure_is_unwrapped(): void
    {
        $page = $this->renderPage([
            'order' => fn () => Inertia::live('bar', on: new OrderUpdated),
        ]);

        $this->assertSame('bar', $page['props']['order']);
        $this->assertArrayHasKey('order', $page['liveProps']);
    }

    public function test_deferred_props_are_live_on_the_initial_load(): void
    {
        $page = $this->renderPage([
            'orders' => Inertia::defer(fn () => ['bar'])->live(on: new OrderCreated),
        ]);

        $this->assertArrayNotHasKey('orders', $page['props']);
        $this->assertSame(['default' => ['orders']], $page['deferredProps']);

        $this->assertSame([
            'orders' => [
                'listeners' => [
                    [
                        'channel' => ['name' => 'orders', 'type' => 'public'],
                        'events' => ['order.created'],
                    ],
                ],
            ],
        ], $page['liveProps']);
    }

    public function test_optional_props_are_live_on_the_initial_load(): void
    {
        $page = $this->renderPage([
            'order' => Inertia::optional(fn () => 'bar')->live(on: new OrderUpdated),
        ]);

        $this->assertArrayNotHasKey('order', $page['props']);
        $this->assertArrayHasKey('order', $page['liveProps']);
    }

    public function test_always_props_are_live_on_the_initial_load(): void
    {
        $page = $this->renderPage([
            'orders' => Inertia::always(fn () => ['bar'])->live(on: new OrderCreated),
        ]);

        $this->assertSame(['bar'], $page['props']['orders']);
        $this->assertArrayHasKey('orders', $page['liveProps']);
    }

    public function test_deferred_live_props_can_still_append_without_merging(): void
    {
        $page = $this->renderPage([
            'orders' => Inertia::defer(fn () => ['items' => ['bar']])->live(on: new OrderCreated)->append('items'),
        ]);

        $this->assertArrayNotHasKey('orders', $page['props']);
        $this->assertArrayHasKey('orders', $page['liveProps']);
    }

    public function test_once_props_the_client_already_loaded_are_still_live(): void
    {
        $page = $this->renderPage(
            ['order' => Inertia::once(fn () => 'bar')->live(on: new OrderUpdated)],
            ['X-Inertia' => 'true', 'X-Inertia-Except-Once-Props' => 'order'],
        );

        $this->assertArrayNotHasKey('order', $page['props']);
        $this->assertArrayHasKey('order', $page['liveProps']);
    }

    public function test_the_live_manifest_is_serialized_on_partial_requests(): void
    {
        $page = $this->renderPage([
            'order' => Inertia::live(fn () => 'bar', on: new OrderUpdated),
            'metrics' => Inertia::live(fn () => 'baz', on: new OrderShipped),
        ], [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'User/Edit',
            'X-Inertia-Partial-Data' => 'order',
        ]);

        $this->assertSame(['order' => 'bar'], $page['props']);
        $this->assertSame(['order'], array_keys($page['liveProps']));
    }

    public function test_live_always_props_keep_metadata_on_unrelated_partial_requests(): void
    {
        $page = $this->renderPage([
            'order' => Inertia::always(fn () => 'bar')->live(on: new OrderUpdated(2)),
            'metrics' => Inertia::live(fn () => 'baz', on: new OrderShipped),
            'status' => Inertia::live(fn () => 'ready', on: new OrderCreated),
        ], [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'User/Edit',
            'X-Inertia-Partial-Data' => 'metrics',
        ]);

        $this->assertSame(['order' => 'bar', 'metrics' => 'baz'], $page['props']);
        $this->assertArrayHasKey('order', $page['liveProps']);
        $this->assertSame([['channel' => ['name' => 'orders.2', 'type' => 'private'], 'events' => [OrderUpdated::class]]], $page['liveProps']['order']['listeners']);
        $this->assertArrayHasKey('metrics', $page['liveProps']);
        $this->assertArrayNotHasKey('status', $page['liveProps']);
    }

    public function test_live_props_under_an_always_parent_keep_metadata_on_unrelated_partial_requests(): void
    {
        $page = $this->renderPage([
            'order' => Inertia::always(fn () => ['total' => Inertia::live(fn () => 100, on: new OrderUpdated(2))]),
            'metrics' => Inertia::live(fn () => 'baz', on: new OrderShipped),
        ], [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'User/Edit',
            'X-Inertia-Partial-Data' => 'metrics',
        ]);

        // The value is sent because its parent resolved outside the filter, so
        // its listeners have to be sent with it
        $this->assertSame(['order' => ['total' => 100], 'metrics' => 'baz'], $page['props']);
        $this->assertArrayHasKey('order.total', $page['liveProps']);
        $this->assertSame([['channel' => ['name' => 'orders.2', 'type' => 'private'], 'events' => [OrderUpdated::class]]], $page['liveProps']['order.total']['listeners']);
    }

    public function test_live_props_under_an_always_parent_keep_metadata_when_excepted(): void
    {
        $page = $this->renderPage([
            'order' => Inertia::always(fn () => ['total' => Inertia::live(fn () => 100, on: new OrderUpdated(3))]),
        ], [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'User/Edit',
            'X-Inertia-Partial-Except' => 'order',
        ]);

        // An always prop outranks [except], so its children are sent and their
        // listeners have to be sent too
        $this->assertSame(['order' => ['total' => 100]], $page['props']);
        $this->assertArrayHasKey('order.total', $page['liveProps']);
    }

    public function test_nested_live_props_are_keyed_by_their_dot_path(): void
    {
        $page = $this->renderPage([
            'order' => ['total' => Inertia::live(fn () => 100, on: new OrderUpdated)],
        ]);

        $this->assertSame(['order' => ['total' => 100]], $page['props']);
        $this->assertSame(['order.total'], array_keys($page['liveProps']));
    }

    /**
     * Render a page and return its page object.
     *
     * @param  array<string, mixed>  $props
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    protected function renderPage(array $props, array $headers = ['X-Inertia' => 'true']): array
    {
        $request = Request::create('/user/123', 'GET');
        $request->headers->add($headers);

        $response = new Response('User/Edit', [], $props, 'app', '123');

        /** @var JsonResponse $response */
        $response = $response->toResponse($request);

        return $response->getData(true);
    }
}
