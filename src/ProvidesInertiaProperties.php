<?php

namespace Inertia;

interface ProvidesInertiaProperties
{
    /**
     * @return array<string, mixed>
     */
    public function toInertiaProperties(RenderContext $context): iterable;
}
