<?php

namespace Inertia\Tests\Stubs;

use Inertia\Inertia;
use Inertia\PropContext;
use Inertia\ProvidesInertiaProp;

class MergeWithSharedProp implements ProvidesInertiaProp
{
    public function __construct(protected array $items = []) {}

    public function toInertiaProp(PropContext $prop): mixed
    {
        return array_merge(Inertia::getShared($prop->key, []), $this->items);
    }
}
