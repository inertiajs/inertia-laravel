<?php

namespace Inertia\Tests;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Inertia\DevTools\EntryStore;
use Inertia\DevTools\RequestAttribute;
use Inertia\Inertia;
use Inertia\ProvidesInertiaProperties;
use Inertia\RenderContext;
use Inertia\Response;
use Inertia\Support\Header;
use RuntimeException;

class BroadcastOrderResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @var array{id: int, total: int}
     */
    private array $order;

    /**
     * @param  array{id: int, total: int}  $resource
     */
    public function __construct($resource)
    {
        parent::__construct($resource);

        $this->order = $resource;
    }

    /**
     * @return array{id: int, total: int}
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->order['id'],
            'total' => $this->order['total'],
        ];
    }
}

/**
 * @implements Arrayable<string, mixed>
 */
class BroadcastOrderSummary implements Arrayable
{
    public function toArray(): array
    {
        return [
            'id' => 1,
            'total' => 125,
        ];
    }
}

class BroadcastOrderUpdated
{
    /**
     * @return array{id: int, event: string, __inertia: array{props: array{status: string}}}
     */
    public function broadcastWith(): array
    {
        return [
            'id' => 1,
            'event' => 'order.updated',
            ...Inertia::broadcastProps([
                'status' => 'paid',
            ]),
        ];
    }
}

class BroadcastOrderProperties implements ProvidesInertiaProperties
{
    /**
     * @return array{id: int, status: string}
     */
    public function toInertiaProperties(RenderContext $context): array
    {
        return [
            'id' => 1,
            'status' => 'paid',
        ];
    }
}

class BroadcastPropsTest extends TestCase
{
    public function test_returns_the_inertia_props_envelope_and_nothing_else(): void
    {
        $payload = $this->broadcastProps([
            'order' => ['id' => 1],
        ]);

        $this->assertSame(['__inertia'], array_keys($payload));
        $this->assertSame(['props'], array_keys($payload['__inertia']));
        $this->assertSame(['order' => ['id' => 1]], $payload['__inertia']['props']);
    }

    public function test_a_json_resource_value_resolves_to_the_same_array_as_a_page_prop(): void
    {
        $order = ['id' => 1, 'total' => 125];

        $page = $this->renderPage([
            'order' => BroadcastOrderResource::make($order),
        ]);

        $payload = $this->broadcastProps([
            'order' => BroadcastOrderResource::make($order),
        ]);

        $this->assertSame($page['props']['order'], $payload['__inertia']['props']['order']);
    }

    public function test_a_closure_value_resolves(): void
    {
        $payload = $this->broadcastProps([
            'path' => fn (Request $request) => $request->path(),
        ], Request::create('/orders/1', 'GET'));

        $this->assertSame('orders/1', $payload['__inertia']['props']['path']);
    }

    public function test_an_arrayable_value_resolves(): void
    {
        $payload = $this->broadcastProps([
            'order' => new BroadcastOrderSummary,
        ]);

        $this->assertSame(['id' => 1, 'total' => 125], $payload['__inertia']['props']['order']);
    }

    public function test_every_prop_named_in_the_payload_is_resolved(): void
    {
        $payload = $this->broadcastProps([
            'order' => ['id' => 1],
            'deferred' => Inertia::defer(fn () => 'deferred-value'),
            'optional' => Inertia::optional(fn () => 'optional-value'),
        ]);

        $this->assertSame([
            'order' => ['id' => 1],
            'deferred' => 'deferred-value',
            'optional' => 'optional-value',
        ], $payload['__inertia']['props']);
    }

    public function test_a_rescued_prop_is_left_out_of_the_payload(): void
    {
        $payload = $this->broadcastProps([
            'order' => ['id' => 1],
            'stats' => Inertia::defer(function () {
                throw new RuntimeException('Rescue this broadcast prop');
            }, rescue: true),
        ]);

        $this->assertSame(['order' => ['id' => 1]], $payload['__inertia']['props']);
        $this->assertArrayNotHasKey('stats', $payload['__inertia']['props']);
    }

    public function test_a_once_prop_the_requesting_client_already_has_is_still_broadcast(): void
    {
        $request = Request::create('/orders/1', 'GET');
        $request->headers->add([
            Header::INERTIA => 'true',
            Header::EXCEPT_ONCE_PROPS => 'settings',
        ]);

        $payload = $this->broadcastProps([
            'settings' => Inertia::once(fn () => ['theme' => 'dark']),
        ], $request);

        $this->assertSame(['theme' => 'dark'], $payload['__inertia']['props']['settings']);
    }

    public function test_partial_reload_headers_do_not_filter_the_payload(): void
    {
        $request = Request::create('/orders/1', 'GET');
        $request->headers->add([
            Header::INERTIA => 'true',
            Header::PARTIAL_COMPONENT => 'Orders/Show',
            Header::PARTIAL_EXCEPT => 'order',
        ]);

        $payload = $this->broadcastProps([
            'order' => ['id' => 1],
        ], $request);

        $this->assertSame(['id' => 1], $payload['__inertia']['props']['order']);
    }

    public function test_broadcasting_during_a_page_render_does_not_record_the_props_on_the_devtools_entry(): void
    {
        config()->set('inertia.devtools.enabled', true);
        config()->set('inertia.devtools.except', []);

        $request = Request::create('/orders/1', 'GET');
        $request->headers->add([Header::INERTIA => 'true']);
        $this->bindRequest($request);

        $response = Inertia::render('Orders/Show', ['order' => ['id' => 1]]);

        Inertia::broadcastProps(['broadcastOnlyStatus' => 'paid']);

        $response->toResponse($request);

        $payload = $request->attributes->get(RequestAttribute::PAYLOAD);

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('order', $payload['props']);
        $this->assertArrayNotHasKey('broadcastOnlyStatus', $payload['props']);
    }

    public function test_an_always_broadcast_prop_still_resolves(): void
    {
        $payload = $this->broadcastProps([
            'always' => Inertia::always('always-value'),
        ]);

        $this->assertSame('always-value', $payload['__inertia']['props']['always']);
    }

    public function test_dot_path_keys_stay_flat_and_literal(): void
    {
        $payload = $this->broadcastProps([
            'order.total' => 125,
        ]);

        $this->assertSame(['order.total' => 125], $payload['__inertia']['props']);
        $this->assertArrayNotHasKey('order', $payload['__inertia']['props']);
    }

    public function test_the_authors_own_sibling_keys_in_broadcast_with_are_untouched(): void
    {
        $this->bindRequest(Request::create('/orders/1', 'GET'));

        $payload = (new BroadcastOrderUpdated)->broadcastWith();

        $this->assertSame(['id', 'event', '__inertia'], array_keys($payload));
        $this->assertSame(1, $payload['id']);
        $this->assertSame('order.updated', $payload['event']);
        $this->assertSame(['status' => 'paid'], $payload['__inertia']['props']);
    }

    public function test_multiple_props_resolve_in_one_call(): void
    {
        $payload = $this->broadcastProps([
            'order' => ['id' => 1],
            'status' => 'paid',
        ]);

        $this->assertSame([
            'order' => ['id' => 1],
            'status' => 'paid',
        ], $payload['__inertia']['props']);
    }

    public function test_a_numerically_keyed_property_provider_contributes_its_own_keys(): void
    {
        $payload = $this->broadcastProps([
            new BroadcastOrderProperties,
        ]);

        $this->assertSame(['id' => 1, 'status' => 'paid'], $payload['__inertia']['props']);
    }

    public function test_constructing_the_broadcast_resolver_does_not_record_a_devtools_entry(): void
    {
        config()->set('inertia.devtools.enabled', true);
        config()->set('inertia.devtools.except', []);

        $request = Request::create('/orders/1', 'GET');

        $this->broadcastProps([
            'order' => fn () => ['id' => 1],
        ], $request);

        $this->assertNull($this->app->make(EntryStore::class)->current());
        $this->assertFalse($request->attributes->has(RequestAttribute::PAYLOAD));
    }

    /**
     * @param  array<array-key, mixed>  $props
     * @return array{__inertia: array{props: array<array-key, mixed>}}
     */
    protected function broadcastProps(array $props, ?Request $request = null): array
    {
        $this->bindRequest($request ?? Request::create('/broadcast', 'GET'));

        return Inertia::broadcastProps($props);
    }

    protected function bindRequest(Request $request): void
    {
        $this->app->instance('request', $request);
    }

    /**
     * @param  array<array-key, mixed>  $props
     * @return array<string, mixed>
     */
    protected function renderPage(array $props): array
    {
        $request = Request::create('/orders/1', 'GET');
        $request->headers->add(['X-Inertia' => 'true']);

        $response = (new Response('Orders/Show', [], $props, 'app', '123'))->toResponse($request);

        $this->assertInstanceOf(JsonResponse::class, $response);

        return $response->getData(true);
    }
}
