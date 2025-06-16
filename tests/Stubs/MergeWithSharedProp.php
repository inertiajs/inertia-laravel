<?php

namespace Inertia\Tests\Stubs;

use Inertia\Inertia;
use Inertia\InertiaResponsable;
use Inertia\Prop;

class MergeWithSharedProp implements InertiaResponsable
{
    public function __construct(protected array $items = []) {}

    public function toInertiaResponse(Prop $prop): mixed
    {
        return array_merge(Inertia::getShared($prop->key, []), $this->items);
    }
}
