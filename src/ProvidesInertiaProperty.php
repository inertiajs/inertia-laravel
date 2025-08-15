<?php

namespace Inertia;

interface ProvidesInertiaProperty
{
    /**
     * Convert the instance to an Inertia property value. This method is called
     * when the object is used as a property value in an Inertia response,
     * allowing for custom serialization.
     */
    public function toInertiaProperty(PropertyContext $prop): mixed;
}
