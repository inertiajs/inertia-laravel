<?php

namespace Inertia\Tests\Stubs;

use Inertia\Inertia;
use Inertia\PropertyContext;
use Inertia\ProvidesInertiaProperty;

class MergeWithSharedProp implements ProvidesInertiaProperty
{
    /**
     * @param  array<int, mixed>  $items
     */
    public function __construct(protected array $items = []) {}

    public function toInertiaProperty(PropertyContext $prop): mixed
    {
        return array_merge(Inertia::getShared($prop->key, []), $this->items);
    }
}
