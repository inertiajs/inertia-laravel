<?php

namespace Inertia\Tests\Stubs;

use Inertia\Ssr\Gateway;
use Inertia\Ssr\Response;

class FakeGateway implements Gateway
{
    /**
     * Dispatch the Inertia page to the Server Side Rendering engine.
     *
     * @param  array  $page
     * @return Response|null
     */
    public function dispatch(array $page): ?Response
    {
        if ($page['component'] === 'Ssr/Fail') {
            return null;
        }

        return new Response(
            ['foo', 'bar'],
            'baz'
        );
    }
}
