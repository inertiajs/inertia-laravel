<?php

namespace Inertia\DevTools;

use Illuminate\Http\Request;

class DevToolsHeader
{
    /**
     * Response header stamped on every response with the entry's ULID.
     */
    public const DEVTOOLS_ID = 'X-Inertia-Devtools-Id';

    /**
     * Response header carrying the path the app is served from, set only when it is not
     * the origin root. The extension fetches the entry endpoint itself, and knows the
     * origin but not where within it the app lives.
     */
    public const DEVTOOLS_BASE_PATH = 'X-Inertia-Devtools-Base-Path';

    /**
     * Response header set on every response, carrying the batch root id. The client
     * forwards it as DEVTOOLS_INCOMING_PARENT on the next same-batch request so
     * follow-ups (including those after a redirect) attach to the batch root.
     */
    public const DEVTOOLS_OUTGOING_PARENT = 'X-Inertia-Devtools-Parent-Out';

    /**
     * Request header forwarded by the client to stamp the entry's batchId.
     */
    public const DEVTOOLS_INCOMING_PARENT = 'X-Inertia-Devtools-Parent';

    /**
     * Request header carrying the extension-assigned per-tab UUID.
     */
    public const DEVTOOLS_TAB = 'X-Inertia-Devtools-Tab';

    /**
     * Request header carrying the client visit id, used to correlate the recorded
     * entry with the browser-side page state in the devtools extension.
     */
    public const DEVTOOLS_VISIT = 'X-Inertia-Devtools-Visit';

    /**
     * Request header the client stamps on a deferred-prop follow-up visit. On the
     * wire a deferred fetch is indistinguishable from a manual partial reload, so
     * the extension forwards the client's deferred intent for the recorder to read.
     */
    public const DEVTOOLS_DEFERRED = 'X-Inertia-Devtools-Deferred';

    /**
     * Request header the client stamps on a polling visit. On the wire a poll
     * tick is indistinguishable from a manual reload or partial, so the
     * extension forwards the client's poll intent for the recorder to read.
     */
    public const DEVTOOLS_POLL = 'X-Inertia-Devtools-Poll';

    /**
     * Read a request header, returning null when it is absent or empty.
     */
    public static function read(Request $request, string $header): ?string
    {
        $value = $request->header($header);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
