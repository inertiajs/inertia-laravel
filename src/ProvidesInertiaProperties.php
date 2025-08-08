<?php

namespace Inertia;

interface ProvidesInertiaProperties
{
    public function toInertiaProperties(RenderContext $context): iterable;
}
