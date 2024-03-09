<?php

namespace Inertia\Testing\Listeners;

use Illuminate\Foundation\Http\Events\RequestHandled;

class RequestHandledListener
{
    public function handle(RequestHandled $event): void
    {
        app()->bind('inertia.testing.request', function () use ($event) {
            return $event->request;
        });
    }
}
