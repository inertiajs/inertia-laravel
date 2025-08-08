<?php

namespace Inertia;

interface ProvidesInertiaProperty
{
    public function toInertiaProperty(PropertyContext $prop): mixed;
}
