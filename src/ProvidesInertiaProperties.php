<?php

namespace Inertia;

interface ProvidesInertiaProperties
{
    public function toInertiaProps(RenderContext $context): iterable;
}
