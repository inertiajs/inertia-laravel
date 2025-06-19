<?php

namespace Inertia;

interface ProvidesInertiaProps
{
    public function toInertiaProps(RenderContext $context): iterable;
}
