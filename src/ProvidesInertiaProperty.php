<?php

namespace Inertia;

interface ProvidesInertiaProperty
{
    /**
     * Convert the instance to an Inertia property.
     */
    public function toInertiaProperty(PropertyContext $prop): mixed;
}
