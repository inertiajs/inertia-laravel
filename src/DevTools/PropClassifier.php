<?php

namespace Inertia\DevTools;

use Illuminate\Http\Request;
use Inertia\AlwaysProp;
use Inertia\DeferProp;
use Inertia\Deferrable;
use Inertia\DevTools\Data\PropType;
use Inertia\Mergeable;
use Inertia\MergeProp;
use Inertia\Onceable;
use Inertia\OnceProp;
use Inertia\OptionalProp;
use Inertia\ScrollProp;
use Inertia\Support\Header;

/**
 * Maps Inertia prop wrapper instances to the stable metadata the extension renders.
 * Pure classification: returns normalized arrays, never touches the collector or any
 * global request singleton (the request is passed in explicitly).
 */
class PropClassifier
{
    /**
     * @return array{inertiaType: ?PropType, deferGroup: ?string, reset: bool, once: bool, mergeDirection: ?string, deepMerge: bool}
     */
    public function classifyResolved(string $path, mixed $prop, Request $request): array
    {
        // A DeferProp only counts as deferred when it is delivered by a deferred request (the
        // auto follow-up load). Resolved on a manual partial reload it behaves like a regular
        // prop, so it drops its defer type and group and reads as a plain partial prop.
        $isDeferredDelivery = $prop instanceof DeferProp && $this->isDeferredRequest($request);

        return [
            'inertiaType' => $this->classifyInertiaWrapper($prop, $isDeferredDelivery),
            'deferGroup' => $this->deferGroup($prop, $isDeferredDelivery),
            'reset' => in_array($path, $this->parseDevToolsHeader($request, Header::RESET), true),
            'once' => $prop instanceof Onceable && $prop->shouldResolveOnce(),
            'mergeDirection' => $this->mergeDirection($prop),
            'deepMerge' => $this->isDeepMerge($prop),
        ];
    }

    protected function isDeferredRequest(Request $request): bool
    {
        return (bool) $request->header(DevToolsHeader::DEVTOOLS_DEFERRED);
    }

    /**
     * The defer group applies to a genuinely deferred DeferProp, and to other deferrable props
     * (e.g. ScrollProp) as before. A DeferProp reloaded outside a deferred request carries none.
     */
    protected function deferGroup(mixed $prop, bool $isDeferredDelivery): ?string
    {
        if (! $prop instanceof Deferrable || ! $prop->shouldDefer()) {
            return null;
        }

        if ($prop instanceof DeferProp && ! $isDeferredDelivery) {
            return null;
        }

        return $prop->group();
    }

    /**
     * A prop is a deep merge when it deep-merges nested data (`->deepMerge()`) or matches
     * array items on a key (`->matchOn()`) to upsert them rather than blindly appending.
     */
    protected function isDeepMerge(mixed $prop): bool
    {
        return $prop instanceof Mergeable && ($prop->shouldDeepMerge() || count($prop->matchesOn()) > 0);
    }

    /**
     * Resolve how a merge/scroll prop combines with existing client data. Direction is read
     * from the prop wrapper (not the page-object arrays) so it survives deep merges, which
     * the page object records only under `deepMergeProps` without a direction.
     */
    protected function mergeDirection(mixed $prop): ?string
    {
        if (! $prop instanceof Mergeable || ! $prop->shouldMerge()) {
            return null;
        }

        $prependsNested = count($prop->prependsAtPaths()) > 0;
        $appendsNested = count($prop->appendsAtPaths()) > 0;

        if ($prop->prependsAtRoot() || ($prependsNested && ! $appendsNested)) {
            return 'prepend';
        }

        return 'append';
    }

    /**
     * Classify an Inertia prop wrapper instance into a stable token the extension
     * renders as a type pill.
     */
    protected function classifyInertiaWrapper(mixed $prop, bool $isDeferredDelivery): ?PropType
    {
        return match (true) {
            $prop instanceof AlwaysProp => PropType::Always,
            $prop instanceof DeferProp => $isDeferredDelivery ? PropType::Defer : null,
            $prop instanceof OptionalProp => PropType::Optional,
            $prop instanceof MergeProp => PropType::Merge,
            $prop instanceof ScrollProp => PropType::Scroll,
            $prop instanceof OnceProp => PropType::Once,
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    protected function parseDevToolsHeader(Request $request, string $key): array
    {
        return array_filter(explode(',', (string) $request->header($key, '')));
    }
}
