<?php

namespace Inertia\Tests\Stubs;

use Inertia\Ssr\Gateway;
use Inertia\Ssr\Response;
use Illuminate\Support\Facades\Config;

class FakeGateway implements Gateway
{
    /**
     * Tracks the number of times the 'dispatch' method was called.
     *
     * @var int
     */
    public $times = 0;

    /**
     * Dispatch the Inertia page to the Server Side Rendering engine.
     */
    public function dispatch(array $page): ?Response
    {
        $this->times++;

        if (! Config::get('inertia.ssr.enabled', false)) {
            return null;
        }

        return new Response(
            "<meta charset=\"UTF-8\" />\n<title inertia>Example SSR Title</title>\n",
            '<p>This is some example SSR content</p>'
        );
    }
}
