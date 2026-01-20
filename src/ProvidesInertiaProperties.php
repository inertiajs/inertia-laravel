<?php

namespace Inertia;

interface ProvidesInertiaProperties
{
    /**
     * Get the properties to be provided to Inertia. This method allows objects
     * to dynamically provide properties that will be serialized and sent
     * to the frontend.
     *
     * @return iterable<string, mixed>
     */
    public function toInertiaProperties(RenderContext $context): iterable;
}
