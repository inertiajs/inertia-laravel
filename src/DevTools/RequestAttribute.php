<?php

namespace Inertia\DevTools;

/**
 * Keys for values stashed on the request attribute bag ($request->attributes) during
 * the devtools request lifecycle. Unlike DevToolsHeader, these never leave the process:
 * they carry state between the recorder, the collector, and the entry builder.
 */
class RequestAttribute
{
    /**
     * High-resolution start time (hrtime) stamped when the request begins, used to
     * compute the entry's server timing.
     */
    public const START = 'inertia_devtools_start';

    /**
     * The collector payload (component, props, propValues, route, sources) captured
     * during rendering and merged into the entry.
     */
    public const PAYLOAD = 'inertia_devtools_payload';
}
