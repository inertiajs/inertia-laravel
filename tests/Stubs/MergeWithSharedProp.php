<?php

namespace Inertia\Tests\Stubs;

use Inertia\Inertia;
use Inertia\InertiaResponsible;
use Inertia\Prop;

class MergeWithSharedProp implements InertiaResponsible
{
    public function __construct(protected array $items = []) {}

    public function toInertiaResponse(Prop $prop): mixed
    {
        return array_merge(Inertia::getShared($prop->key, []), $this->items);
    }
}
